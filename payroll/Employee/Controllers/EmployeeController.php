<?php

namespace Payroll\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Salary;
use App\Models\User;
use DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Log;
use Payroll\Audit\Traits\Auditable;
use Payroll\Employee\Enums\DayOfWeek;
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

        $unlinkedUsers = auth()->user()->isSuperAdmin()
            ? User::whereNull('employee_id')->whereIn('role', ['admin', 'staff'])->get(['id', 'first_name', 'last_name', 'username', 'branch_id'])
            : User::whereNull('employee_id')->where('branch_id', auth()->user()->branch_id)->whereIn('role', ['admin', 'staff'])->get(['id', 'first_name', 'last_name', 'username', 'branch_id']);

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
            'unlinkedUsers' => $unlinkedUsers,
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

                $username = strtolower($employee->first_name.$employee->last_name.bin2hex(random_bytes(2)));
                $user = User::create([
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'username' => $username,
                    'password' => bcrypt(bin2hex(random_bytes(8))),
                    'role' => 'staff',
                    'branch_id' => $employee->branch_id,
                    'employee_id' => $employee->id,
                ]);

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

        $employee->load(['branch', 'salaries', 'benefits', 'projects', 'schedules']);

        return Inertia::render('payroll/employees/show', [
            'employee' => $employee,
            'daysOfWeek' => collect(DayOfWeek::cases())->map(fn ($d) => [
                'value' => $d->value,
                'label' => $d->label(),
            ])->values(),
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
        $newStatus = $request->input('status');
        $oldStatus = $employee->status->value;

        if ($oldStatus === 'active' && $newStatus !== 'active') {
            $this->authorize('deactivate', $employee);
        }

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

    public function updateSelf(UpdateSelfEmployeeRequest $request)
    {
        $employee = Employee::findOrFail(auth()->user()->employee_id);

        $before = $employee->getAttributes();

        try {
            $employee->update($request->validated());

            $employee->refresh();
            $this->audit('self_updated', $employee, $before, $employee->getAttributes());

            return back()->with('success', 'Profile updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update self profile', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Failed to update profile. Please try again.']);
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

    public function linkUser(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($request->input('user_id'));

        if ($user->branch_id != $employee->branch_id) {
            return back()->withErrors(['error' => 'User and employee must belong to the same branch.']);
        }

        $existingEmployee = User::where('employee_id', $employee->id)->exists();
        if ($existingEmployee) {
            return back()->withErrors(['error' => 'Employee already linked to a user.']);
        }

        $user->update(['employee_id' => $employee->id]);

        return back()->with('success', "{$user->fullname} linked to {$employee->full_name}.");
    }

    public function unlinkUser(Employee $employee)
    {
        $this->authorize('update', $employee);

        $user = User::where('employee_id', $employee->id)->first();

        if (! $user) {
            return back()->withErrors(['error' => 'No user linked to this employee.']);
        }

        $user->update(['employee_id' => null]);

        return back()->with('success', "{$user->fullname} unlinked from {$employee->full_name}.");
    }

    public function syncUser(Request $request, User $user)
    {
        $this->authorize('create', Employee::class);

        if ($user->employee_id) {
            return back()->withErrors(['error' => 'User already linked to an employee.']);
        }

        $employee = DB::transaction(function () use ($user) {
            $employee = Employee::create([
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'branch_id' => $user->branch_id,
                'hire_date' => now()->toDateString(),
                'position' => 'regular',
                'status' => 'active',
                'current_daily_rate' => 0,
                'email' => null,
                'phone' => null,
                'address' => null,
                'birth_date' => null,
                'end_date' => null,
                'sss_number' => null,
                'philhealth_number' => null,
                'pagibig_number' => null,
                'tin_number' => null,
                'notes' => null,
            ]);

            $user->update(['employee_id' => $employee->id]);

            return $employee;
        });

        $this->audit('created', $employee, [], $employee->getAttributes());

        return back()->with('success', "{$employee->full_name} created and linked to {$user->fullname}.");
    }
}
