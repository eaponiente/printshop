<?php

namespace Payroll\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Payroll\Audit\Traits\Auditable;
use Payroll\Employee\Enums\DayOfWeek;
use Payroll\Employee\Enums\EmployeePosition;
use Payroll\Employee\Enums\EmployeeStatus;
use Payroll\Employee\Requests\LinkUserRequest;
use Payroll\Employee\Requests\RehireEmployeeRequest;
use Payroll\Employee\Requests\StoreEmployeeRequest;
use Payroll\Employee\Requests\SyncAllRequest;
use Payroll\Employee\Requests\SyncUserRequest;
use Payroll\Employee\Requests\UpdateEmployeeRequest;
use Payroll\Employee\Requests\UpdateSelfEmployeeRequest;
use Payroll\Employee\Services\EmployeeService;

class EmployeeController extends Controller
{
    use Auditable;
    use AuthorizesRequests;

    public function __construct(
        private readonly EmployeeService $service,
    ) {}

    public function index(Request $request)
    {
        $branchId = auth()->user()->isAdmin() || auth()->user()->isStaff()
            ? auth()->user()->branch_id
            : null;

        $filters = $request->query('filters', []);

        if (! is_array($filters)) {
            $filters = [];
        }

        $employees = $this->service
            ->buildIndexQuery($filters, $branchId)
            ->paginate(50)
            ->withQueryString();

        $today = now()->toDateString();
        $employees->through(function ($employee) use ($today) {
            $data = $employee->toArray();
            $activeSchedule = $this->service->computeActiveSchedule($employee, $today);
            $data['active_schedule'] = $activeSchedule ? $activeSchedule->toArray() : null;

            return $data;
        });

        $unlinkedUsers = $this->service->getUnlinkedUsers(
            auth()->user()->isSuperAdmin(),
            auth()->user()->branch_id,
        );

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
            'filterColumns' => collect([
                'employee_number',
                'first_name',
                'last_name',
                'position',
                'status',
            ])->map(fn ($col) => [
                'key' => $col,
                'value' => str_replace('_', ' ', ucfirst($col)),
            ])->values(),
            'filters' => $filters,
            'unlinkedUsers' => $unlinkedUsers,
            'isSuperAdmin' => auth()->user()->isSuperAdmin(),
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
        $employee = $this->service->create($request->validated());
        $this->audit('created', $employee, [], $employee->getAttributes());

        return redirect()->route('payroll.employees.index')
            ->with('success', 'Employee created successfully.');
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

        $employee->load(['branch', 'salaries', 'user']);

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
        $before = $employee->getAttributes();
        $newStatus = $request->input('status');
        $oldStatus = $employee->status->value;

        if ($oldStatus === 'active' && $newStatus !== 'active') {
            $this->authorize('deactivate', $employee);
        }

        $this->service->update($employee, $request->validated());

        $employee->refresh();
        $this->audit('updated', $employee, $before, $employee->getAttributes());

        return redirect()->route('payroll.employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    public function updateSelf(UpdateSelfEmployeeRequest $request)
    {
        $employee = Employee::findOrFail(auth()->user()->employee_id);

        $before = $employee->getAttributes();
        $employee->update($request->validated());
        $employee->refresh();

        $this->audit('self_updated', $employee, $before, $employee->getAttributes());

        return back()->with('success', 'Profile updated successfully.');
    }

    public function deactivate(Employee $employee)
    {
        $this->authorize('deactivate', $employee);

        $before = $employee->getAttributes();

        $hasRelatedRecords = $this->service->hasRelatedRecords($employee);
        $isSuperAdmin = auth()->user()->isSuperAdmin();

        if (! $hasRelatedRecords && $isSuperAdmin) {
            $this->service->delete($employee);
            $employee->refresh();
            $this->audit('deleted', $employee, $before, $employee->getAttributes());

            return back()->with('success', 'Employee deleted successfully.');
        }

        $this->service->deactivate($employee);
        $employee->refresh();
        $this->audit('deactivated', $employee, $before, $employee->getAttributes());

        return back()->with('success', 'Employee deactivated successfully.');
    }

    public function rehire(RehireEmployeeRequest $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $before = $employee->getAttributes();
        $this->service->rehire($employee, $request->validated());

        $employee->refresh();
        $this->audit('rehired', $employee, $before, $employee->getAttributes());

        return redirect()->route('payroll.employees.index')
            ->with('success', 'Employee rehired successfully.');
    }

    public function linkUser(LinkUserRequest $request, Employee $employee)
    {
        $this->service->link($employee, (int) $request->validated('user_id'));

        $user = User::findOrFail($request->validated('user_id'));

        return back()->with('success', "{$user->fullname} linked to {$employee->full_name}.");
    }

    public function unlinkUser(Employee $employee)
    {
        $this->authorize('update', $employee);

        $user = User::where('employee_id', $employee->id)->first();

        if (! $user) {
            return back()->withErrors(['error' => 'No user linked to this employee.']);
        }

        $this->service->unlink($employee);

        return back()->with('success', "{$user->fullname} unlinked from {$employee->full_name}.");
    }

    public function syncUser(SyncUserRequest $request, User $user)
    {
        $employee = $this->service->syncUser($user);
        $this->audit('created', $employee, [], $employee->getAttributes());

        return back()->with('success', "{$employee->full_name} created and linked to {$user->fullname}.");
    }

    public function syncAll(SyncAllRequest $request)
    {
        $created = $this->service->syncAll();

        if ($created === 0) {
            return back()->with('success', 'All users are already linked to employees.');
        }

        return back()->with('success', "{$created} employee(s) created with default schedules.");
    }
}
