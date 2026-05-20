<?php

namespace Payroll\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Salary;
use DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Log;
use Payroll\Audit\Traits\Auditable;
use Payroll\Employee\Enums\EmployeePosition;
use Payroll\Employee\Enums\EmployeeStatus;
use Payroll\Employee\Requests\RehireEmployeeRequest;
use Payroll\Employee\Requests\StoreEmployeeRequest;
use Payroll\Employee\Requests\UpdateEmployeeRequest;

class EmployeeController extends Controller
{
    use Auditable;
    use AuthorizesRequests;

    protected array $filterable = [
        'employee_number',
        'first_name',
        'last_name',
        'position',
        'status',
    ];

    public function index(Request $request)
    {
        $query = Employee::query()->with('branch');

        if (auth()->user()->isStaff() || auth()->user()->isAdmin()) {
            $query->where('branch_id', auth()->user()->branch_id);
        }

        $filters = $request->query('filters', []);

        if (! is_array($filters)) {
            $filters = [];
        }

        foreach ($filters as $filter) {
            $column = $filter['column'] ?? null;
            $value = $filter['value'] ?? null;

            if (! $column || ! $value || ! in_array($column, $this->filterable, true)) {
                continue;
            }

            if (in_array($column, ['position', 'status'], true)) {
                $query->where($column, $value);
            } else {
                $query->where($column, 'like', "%{$value}%");
            }
        }

        $employees = $query->orderBy('last_name')->paginate(50)->withQueryString();

        $filterColumns = collect($this->filterable)->map(fn ($col) => [
            'key' => $col,
            'value' => str_replace('_', ' ', ucfirst($col)),
        ])->values();

        return Inertia::render('payroll/employees/list', [
            'employees' => $employees,
            'statuses' => collect(EmployeeStatus::cases())->map(fn ($s) => [
                'key' => $s->value,
                'value' => $s->value,
            ])->values(),
            'positions' => collect(EmployeePosition::cases())->map(fn ($p) => [
                'key' => $p->value,
                'value' => $p->label(),
            ])->values(),
            'branches' => auth()->user()->isSuperAdmin()
                ? Branch::orderBy('name')->get()
                : Branch::where('id', auth()->user()->branch_id)->get(),
            'filterColumns' => $filterColumns,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Employee::class);

        $branches = auth()->user()->isSuperAdmin()
            ? Branch::orderBy('name')->get()
            : Branch::where('id', auth()->user()->branch_id)->get();

        return Inertia::render('payroll/employees/create', [
            'statuses' => collect(EmployeeStatus::cases())->map(fn ($s) => [
                'key' => $s->value,
                'value' => $s->value,
            ])->values(),
            'positions' => collect(EmployeePosition::cases())->map(fn ($p) => [
                'key' => $p->value,
                'value' => $p->label(),
            ])->values(),
            'branches' => $branches,
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        try {
            $employee = DB::transaction(function () use ($request) {
                $validated = $request->validated();
                $dailyRate = $validated['daily_rate'];
                unset($validated['daily_rate']);

                $validated['current_daily_rate'] = $dailyRate;

                $employee = Employee::create($validated);

                Salary::createForEmployee($employee, $dailyRate, $request->hire_date, 'Initial salary on hire');

                return $employee;
            });

            $this->audit('created', $employee, [], $employee->getAttributes());

            return redirect()->route('payroll.employees.index')
                ->with('success', 'Employee created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create employee', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Failed to create employee. Please try again.']);
        }
    }

    public function show(Employee $employee)
    {
        $this->authorize('view', $employee);

        $employee->load(['branch', 'salaries', 'benefits', 'projects']);

        return Inertia::render('payroll/employees/show', [
            'employee' => $employee,
            'statuses' => collect(EmployeeStatus::cases())->map(fn ($s) => [
                'key' => $s->value,
                'value' => $s->value,
            ])->values(),
            'positions' => collect(EmployeePosition::cases())->map(fn ($p) => [
                'key' => $p->value,
                'value' => $p->label(),
            ])->values(),
        ]);
    }

    public function edit(Employee $employee)
    {
        $this->authorize('update', $employee);

        $employee->load(['branch', 'salaries']);

        $branches = auth()->user()->isSuperAdmin()
            ? Branch::orderBy('name')->get()
            : Branch::where('id', auth()->user()->branch_id)->get();

        return Inertia::render('payroll/employees/edit', [
            'employee' => $employee,
            'statuses' => collect(EmployeeStatus::cases())->map(fn ($s) => [
                'key' => $s->value,
                'value' => $s->value,
            ])->values(),
            'positions' => collect(EmployeePosition::cases())->map(fn ($p) => [
                'key' => $p->value,
                'value' => $p->label(),
            ])->values(),
            'branches' => $branches,
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $before = $employee->getAttributes();

        try {
            DB::transaction(function () use ($request, $employee) {
                $validated = $request->validated();
                $dailyRate = $validated['daily_rate'];
                unset($validated['daily_rate']);

                $salaryChanged = (float) $dailyRate !== (float) $employee->current_daily_rate;

                $validated['current_daily_rate'] = $dailyRate;

                $employee->update($validated);

                if ($salaryChanged) {
                    Salary::createForEmployee(
                        $employee,
                        $dailyRate,
                        now()->toDateString(),
                        'Salary adjusted via employee update',
                    );
                }
            });

            $employee->refresh();
            $this->audit('updated', $employee, $before, $employee->getAttributes());

            return redirect()->route('payroll.employees.index')
                ->with('success', 'Employee updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update employee', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Failed to update employee. Please try again.']);
        }
    }

    public function destroy(Employee $employee)
    {
        $this->authorize('delete', $employee);

        $before = $employee->getAttributes();

        try {
            $employee->delete();
            $employee->refresh();

            $this->audit('deleted', $employee, $before, $employee->getAttributes());

            return back()->with('success', 'Employee deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete employee', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Failed to delete employee.']);
        }
    }

    public function rehire(RehireEmployeeRequest $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $before = $employee->getAttributes();

        try {
            DB::transaction(function () use ($request, $employee) {
                $validated = $request->validated();
                $dailyRate = $validated['daily_rate'];
                $rehireDate = $validated['rehire_date'];
                unset($validated['daily_rate'], $validated['rehire_date']);

                $employee->update([
                    'status' => EmployeeStatus::ACTIVE->value,
                    'end_date' => null,
                    'position' => $validated['position'],
                    'current_daily_rate' => $dailyRate,
                ]);

                Salary::createForEmployee(
                    $employee,
                    $dailyRate,
                    $rehireDate,
                    $validated['notes'] ?? 'Rehired',
                );
            });

            $employee->refresh();
            $this->audit('rehired', $employee, $before, $employee->getAttributes());

            return redirect()->route('payroll.employees.index')
                ->with('success', 'Employee rehired successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to rehire employee', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Failed to rehire employee. Please try again.']);
        }
    }
}
