<?php

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Salary;
use App\Models\User;
use Payroll\Audit\Models\AuditLog;
use Payroll\Employee\Enums\EmployeePosition;
use Payroll\Employee\Enums\EmployeeStatus;

beforeEach(function () {
    $this->branchA = Branch::factory()->create(['name' => 'Branch A']);
    $this->branchB = Branch::factory()->create(['name' => 'Branch B']);

    $this->superadmin = User::factory()->create([
        'branch_id' => null,
        'role' => 'superadmin',
    ]);

    $this->adminA = User::factory()->create([
        'branch_id' => $this->branchA->id,
        'role' => 'admin',
    ]);

    $this->adminB = User::factory()->create([
        'branch_id' => $this->branchB->id,
        'role' => 'admin',
    ]);

    $this->staffA = User::factory()->create([
        'branch_id' => $this->branchA->id,
        'role' => 'staff',
    ]);
});

it('allows superadmin to access the employee index', function () {
    $this->actingAs($this->superadmin)
        ->get(route('payroll.employees.index'))
        ->assertOk();
});

it('allows admin to access the employee index', function () {
    $this->actingAs($this->adminA)
        ->get(route('payroll.employees.index'))
        ->assertOk();
});

it('denies staff access to create employee', function () {
    $this->actingAs($this->staffA)
        ->get(route('payroll.employees.create'))
        ->assertForbidden();
});

it('allows admin to create employee', function () {
    $response = $this->actingAs($this->adminA)
        ->post(route('payroll.employees.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'hire_date' => now()->toDateString(),
            'branch_id' => $this->branchA->id,
            'position' => EmployeePosition::REGULAR->value,
            'status' => EmployeeStatus::ACTIVE->value,
            'daily_rate' => 500,
        ]);

    $response->assertRedirect(route('payroll.employees.index'));
    $response->assertSessionHas('success');

    $employee = Employee::where('first_name', 'Juan')->first();
    expect($employee)->not->toBeNull();
    expect($employee->last_name)->toBe('Dela Cruz');
    expect((float) $employee->current_daily_rate)->toBe(500.0);

    expect($employee->employee_number)->toStartWith('EMP-'.now()->format('Y').'-');

    $salary = $employee->salaries()->first();
    expect($salary)->not->toBeNull();
    expect((float) $salary->daily_rate)->toBe(500.0);
});

it('auto-generates employee number on create', function () {
    $emp1 = Employee::create([
        'first_name' => 'First',
        'last_name' => 'Employee',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);

    $emp2 = Employee::create([
        'first_name' => 'Second',
        'last_name' => 'Employee',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);

    expect($emp1->employee_number)->not->toBe($emp2->employee_number);
    expect($emp2->employee_number)->toEndWith(sprintf('%04d', (int) substr($emp1->employee_number, -4) + 1));
});

it('creates salary record on employee creation', function () {
    $this->actingAs($this->adminA)
        ->post(route('payroll.employees.store'), [
            'first_name' => 'With',
            'last_name' => 'Salary',
            'hire_date' => '2025-01-15',
            'branch_id' => $this->branchA->id,
            'position' => EmployeePosition::CONTRACTUAL->value,
            'status' => EmployeeStatus::ACTIVE->value,
            'daily_rate' => 750,
        ]);

    $employee = Employee::where('first_name', 'With')->first();
    $salary = $employee->salaries()->first();

    expect($salary->effective_date->toDateString())->toBe('2025-01-15');
    expect($salary->end_date)->toBeNull();
    expect((float) $salary->daily_rate)->toBe(750.0);
});

it('creates new salary record when daily rate changes on update', function () {
    $employee = Employee::create([
        'first_name' => 'Rate',
        'last_name' => 'Change',
        'hire_date' => '2024-06-01',
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);
    Salary::createForEmployee($employee, 500, '2024-06-01');

    expect($employee->salaries()->count())->toBe(1);

    $this->actingAs($this->adminA)
        ->put(route('payroll.employees.update', $employee), [
            'first_name' => 'Rate',
            'last_name' => 'Change',
            'hire_date' => '2024-06-01',
            'branch_id' => $this->branchA->id,
            'position' => EmployeePosition::REGULAR->value,
            'status' => EmployeeStatus::ACTIVE->value,
            'daily_rate' => 650,
        ]);

    $employee->refresh();
    expect((float) $employee->current_daily_rate)->toBe(650.0);
    expect($employee->salaries()->count())->toBe(2);

    $currentSalary = $employee->salaries()->whereNull('end_date')->first();
    expect((float) $currentSalary->daily_rate)->toBe(650.0);
});

it('does not create duplicate salary record when daily rate unchanged', function () {
    $employee = Employee::create([
        'first_name' => 'Same',
        'last_name' => 'Rate',
        'hire_date' => '2024-06-01',
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);
    Salary::createForEmployee($employee, 500, '2024-06-01');

    $this->actingAs($this->adminA)
        ->put(route('payroll.employees.update', $employee), [
            'first_name' => 'Same',
            'last_name' => 'Rate',
            'hire_date' => '2024-06-01',
            'branch_id' => $this->branchA->id,
            'position' => EmployeePosition::REGULAR->value,
            'status' => EmployeeStatus::ACTIVE->value,
            'daily_rate' => 500,
        ]);

    expect($employee->salaries()->count())->toBe(1);
});

it('admin only sees employees from own branch', function () {
    Employee::create([
        'first_name' => 'Branch',
        'last_name' => 'AA',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);

    Employee::create([
        'first_name' => 'Branch',
        'last_name' => 'BB',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchB->id,
        'current_daily_rate' => 500,
    ]);

    $response = $this->actingAs($this->adminA)
        ->get(route('payroll.employees.index'));

    $data = $response->inertiaProps('employees')['data'];
    $branchIds = collect($data)->pluck('branch_id')->unique()->values();

    expect($branchIds->toArray())->toEqual([$this->branchA->id]);
});

it('superadmin sees employees from all branches', function () {
    Employee::create([
        'first_name' => 'Branch',
        'last_name' => 'AA',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);

    Employee::create([
        'first_name' => 'Branch',
        'last_name' => 'BB',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchB->id,
        'current_daily_rate' => 500,
    ]);

    $response = $this->actingAs($this->superadmin)
        ->get(route('payroll.employees.index'));

    $data = $response->inertiaProps('employees')['data'];
    $branchIds = collect($data)->pluck('branch_id')->unique()->filter()->values();

    expect($branchIds->toArray())->toEqualCanonicalizing([$this->branchA->id, $this->branchB->id]);
});

it('admin can update employee in same branch', function () {
    $employee = Employee::create([
        'first_name' => 'Updatable',
        'last_name' => 'Employee',
        'middle_name' => 'Old',
        'email' => 'old@example.com',
        'phone' => '09111111111',
        'address' => 'Old Address',
        'birth_date' => '1990-01-01',
        'hire_date' => '2020-01-01',
        'branch_id' => $this->branchA->id,
        'position' => EmployeePosition::REGULAR->value,
        'status' => EmployeeStatus::ACTIVE->value,
        'current_daily_rate' => 500,
        'sss_number' => '00-0000000-0',
        'philhealth_number' => '00-000000000-0',
        'pagibig_number' => '0000-0000-0000',
        'tin_number' => '000-000-000-000',
        'notes' => 'Old notes',
    ]);

    Salary::createForEmployee($employee, 500, '2020-01-01');

    $this->actingAs($this->adminA)
        ->put(route('payroll.employees.update', $employee), [
            'first_name' => 'Updated',
            'last_name' => 'Changed',
            'middle_name' => 'New',
            'email' => 'new@example.com',
            'phone' => '09222222222',
            'address' => 'New Address',
            'birth_date' => '1992-02-02',
            'hire_date' => '2021-01-01',
            'branch_id' => $this->branchA->id,
            'position' => EmployeePosition::CONTRACTUAL->value,
            'status' => EmployeeStatus::ACTIVE->value,
            'daily_rate' => 500,
            'sss_number' => '99-9999999-9',
            'philhealth_number' => '99-999999999-9',
            'pagibig_number' => '9999-9999-9999',
            'tin_number' => '999-999-999-999',
            'notes' => 'Updated notes',
        ])
        ->assertRedirect();

    $employee->refresh();

    expect($employee->first_name)->toBe('Updated');
    expect($employee->last_name)->toBe('Changed');
    expect($employee->middle_name)->toBe('New');
    expect($employee->email)->toBe('new@example.com');
    expect($employee->phone)->toBe('09222222222');
    expect($employee->address)->toBe('New Address');
    expect($employee->birth_date->toDateString())->toBe('1992-02-02');
    expect($employee->hire_date->toDateString())->toBe('2021-01-01');
    expect($employee->position->value)->toBe('contractual');
    expect($employee->sss_number)->toBe('99-9999999-9');
    expect($employee->philhealth_number)->toBe('99-999999999-9');
    expect($employee->pagibig_number)->toBe('9999-9999-9999');
    expect($employee->tin_number)->toBe('999-999-999-999');
    expect($employee->notes)->toBe('Updated notes');

    $log = AuditLog::where('model_id', $employee->id)
        ->where('action', 'updated')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->before)->toBeArray();
    expect($log->after)->toBeArray();

    expect($log->before['hire_date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    expect($log->after['hire_date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    expect($log->before['birth_date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    expect($log->after['birth_date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

it('admin cannot update employee in other branch', function () {
    $employee = Employee::create([
        'first_name' => 'Other',
        'last_name' => 'Branch',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchB->id,
        'current_daily_rate' => 500,
    ]);

    $this->actingAs($this->adminA)
        ->get(route('payroll.employees.edit', $employee))
        ->assertForbidden();
});

it('only superadmin can delete employee', function () {
    $employee = Employee::create([
        'first_name' => 'Deletable',
        'last_name' => 'Employee',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);

    $this->actingAs($this->adminA)
        ->delete(route('payroll.employees.destroy', $employee))
        ->assertForbidden();

    $this->actingAs($this->superadmin)
        ->delete(route('payroll.employees.destroy', $employee))
        ->assertRedirect();

    expect(Employee::withTrashed()->find($employee->id)->deleted_at)->not->toBeNull();
});

it('stores government IDs', function () {
    $this->actingAs($this->adminA)
        ->post(route('payroll.employees.store'), [
            'first_name' => 'Govt',
            'last_name' => 'IDs',
            'hire_date' => now()->toDateString(),
            'branch_id' => $this->branchA->id,
            'position' => EmployeePosition::REGULAR->value,
            'status' => EmployeeStatus::ACTIVE->value,
            'daily_rate' => 500,
            'sss_number' => '12-3456789-0',
            'philhealth_number' => '12-345678901-2',
            'pagibig_number' => '1234-5678-9012',
            'tin_number' => '123-456-789-000',
        ]);

    $employee = Employee::where('first_name', 'Govt')->first();
    expect($employee->sss_number)->toBe('12-3456789-0');
    expect($employee->philhealth_number)->toBe('12-345678901-2');
    expect($employee->pagibig_number)->toBe('1234-5678-9012');
    expect($employee->tin_number)->toBe('123-456-789-000');
});

it('validates required fields on store', function () {
    $response = $this->actingAs($this->adminA)
        ->post(route('payroll.employees.store'), []);

    $response->assertSessionHasErrors([
        'first_name',
        'last_name',
        'hire_date',
        'branch_id',
        'position',
        'status',
        'daily_rate',
    ]);
});

it('admin can view employee in same branch', function () {
    $employee = Employee::create([
        'first_name' => 'View',
        'last_name' => 'Me',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);

    $this->actingAs($this->adminA)
        ->get(route('payroll.employees.show', $employee))
        ->assertOk();
});

it('admin cannot view employee in other branch', function () {
    $employee = Employee::create([
        'first_name' => 'Cant',
        'last_name' => 'View',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchB->id,
        'current_daily_rate' => 500,
    ]);

    $this->actingAs($this->adminA)
        ->get(route('payroll.employees.show', $employee))
        ->assertForbidden();
});

it('can rehire a resigned employee with new salary and position', function () {
    $employee = Employee::create([
        'first_name' => 'Come',
        'last_name' => 'Back',
        'hire_date' => '2023-01-15',
        'end_date' => '2024-12-31',
        'branch_id' => $this->branchA->id,
        'position' => EmployeePosition::REGULAR->value,
        'status' => EmployeeStatus::RESIGNED->value,
        'current_daily_rate' => 500,
    ]);
    Salary::createForEmployee($employee, 500, '2023-01-15');
    Salary::where('employee_id', $employee->id)
        ->whereNull('end_date')
        ->update(['end_date' => '2024-12-31']);

    $this->actingAs($this->adminA)
        ->post(route('payroll.employees.rehire', $employee), [
            'daily_rate' => 700,
            'rehire_date' => '2025-03-01',
            'position' => EmployeePosition::CONTRACTUAL->value,
            'notes' => 'Returning employee, new contract',
        ])
        ->assertRedirect(route('payroll.employees.index'));

    $employee->refresh();
    expect($employee->status->value)->toBe('active');
    expect($employee->end_date)->toBeNull();
    expect($employee->position->value)->toBe('contractual');
    expect((float) $employee->current_daily_rate)->toBe(700.0);

    $currentSalary = $employee->salaries()->whereNull('end_date')->first();
    expect($currentSalary)->not->toBeNull();
    expect($currentSalary->effective_date->toDateString())->toBe('2025-03-01');
    expect((float) $currentSalary->daily_rate)->toBe(700.0);
});

it('prevents rehire with date before end_date', function () {
    $employee = Employee::create([
        'first_name' => 'Invalid',
        'last_name' => 'Rehire',
        'hire_date' => '2023-01-15',
        'end_date' => '2024-12-31',
        'branch_id' => $this->branchA->id,
        'position' => EmployeePosition::REGULAR->value,
        'status' => EmployeeStatus::RESIGNED->value,
        'current_daily_rate' => 500,
    ]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.employees.rehire', $employee), [
            'daily_rate' => 700,
            'rehire_date' => '2024-06-01',
            'position' => EmployeePosition::REGULAR->value,
        ])
        ->assertSessionHasErrors(['rehire_date']);
});

it('preserves salary history after rehire', function () {
    $employee = Employee::create([
        'first_name' => 'History',
        'last_name' => 'Keeper',
        'hire_date' => '2023-01-15',
        'end_date' => '2024-06-30',
        'branch_id' => $this->branchA->id,
        'position' => EmployeePosition::REGULAR->value,
        'status' => EmployeeStatus::RESIGNED->value,
        'current_daily_rate' => 500,
    ]);
    Salary::createForEmployee($employee, 500, '2023-01-15');
    Salary::where('employee_id', $employee->id)
        ->whereNull('end_date')
        ->update(['end_date' => '2024-06-30']);

    $this->actingAs($this->adminA)
        ->post(route('payroll.employees.rehire', $employee), [
            'daily_rate' => 800,
            'rehire_date' => '2025-01-06',
            'position' => EmployeePosition::REGULAR->value,
        ]);

    $employee->refresh();
    expect($employee->salaries()->count())->toBe(2);

    $oldSalary = $employee->salaries()->whereNotNull('end_date')->first();
    expect($oldSalary->effective_date->toDateString())->toBe('2023-01-15');
    expect($oldSalary->end_date->toDateString())->toBe('2024-06-30');
    expect((float) $oldSalary->daily_rate)->toBe(500.0);

    $newSalary = $employee->salaries()->whereNull('end_date')->first();
    expect($newSalary->effective_date->toDateString())->toBe('2025-01-06');
    expect((float) $newSalary->daily_rate)->toBe(800.0);
});

it('filters employees by position', function () {
    Employee::create([
        'first_name' => 'Regular',
        'last_name' => 'One',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'position' => EmployeePosition::REGULAR->value,
        'current_daily_rate' => 500,
    ]);

    Employee::create([
        'first_name' => 'Contract',
        'last_name' => 'Two',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'position' => EmployeePosition::CONTRACTUAL->value,
        'current_daily_rate' => 500,
    ]);

    $response = $this->actingAs($this->adminA)
        ->get(route('payroll.employees.index', [
            'filters' => [
                ['column' => 'position', 'value' => EmployeePosition::REGULAR->value],
            ],
        ]));

    $data = $response->inertiaProps('employees')['data'];
    expect(count($data))->toBe(1);
    expect($data[0]['first_name'])->toBe('Regular');
});

it('filters employees by status', function () {
    Employee::create([
        'first_name' => 'Active',
        'last_name' => 'One',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'status' => EmployeeStatus::ACTIVE->value,
        'current_daily_rate' => 500,
    ]);

    Employee::create([
        'first_name' => 'Resigned',
        'last_name' => 'Two',
        'hire_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'status' => EmployeeStatus::RESIGNED->value,
        'current_daily_rate' => 500,
    ]);

    $response = $this->actingAs($this->adminA)
        ->get(route('payroll.employees.index', [
            'filters' => [
                ['column' => 'status', 'value' => EmployeeStatus::RESIGNED->value],
            ],
        ]));

    $data = $response->inertiaProps('employees')['data'];
    expect(count($data))->toBe(1);
    expect($data[0]['first_name'])->toBe('Resigned');
});

it('filters employees by text search on first_name', function () {
    Employee::create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);

    Employee::create([
        'first_name' => 'Pedro',
        'last_name' => 'Santos',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);

    $response = $this->actingAs($this->adminA)
        ->get(route('payroll.employees.index', [
            'filters' => [
                ['column' => 'first_name', 'value' => 'Juan'],
            ],
        ]));

    $data = $response->inertiaProps('employees')['data'];
    expect(count($data))->toBe(1);
    expect($data[0]['first_name'])->toBe('Juan');
});

it('filters employees by multiple conditions', function () {
    Employee::create([
        'first_name' => 'Keep',
        'last_name' => 'Me',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'position' => EmployeePosition::REGULAR->value,
        'status' => EmployeeStatus::ACTIVE->value,
        'current_daily_rate' => 500,
    ]);

    Employee::create([
        'first_name' => 'Keep',
        'last_name' => 'Two',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'position' => EmployeePosition::CONTRACTUAL->value,
        'status' => EmployeeStatus::ACTIVE->value,
        'current_daily_rate' => 500,
    ]);

    Employee::create([
        'first_name' => 'Skip',
        'last_name' => 'Three',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'position' => EmployeePosition::REGULAR->value,
        'status' => EmployeeStatus::RESIGNED->value,
        'current_daily_rate' => 500,
    ]);

    $response = $this->actingAs($this->adminA)
        ->get(route('payroll.employees.index', [
            'filters' => [
                ['column' => 'position', 'value' => EmployeePosition::REGULAR->value],
                ['column' => 'status', 'value' => EmployeeStatus::ACTIVE->value],
            ],
        ]));

    $data = $response->inertiaProps('employees')['data'];
    expect(count($data))->toBe(1);
    expect($data[0]['last_name'])->toBe('Me');
});

it('returns empty filters in props when no filter applied', function () {
    $response = $this->actingAs($this->adminA)
        ->get(route('payroll.employees.index'));

    $filters = $response->inertiaProps('filters');
    expect($filters)->toBe([]);
});

it('returns active filters in inertia props', function () {
    $response = $this->actingAs($this->adminA)
        ->get(route('payroll.employees.index', [
            'filters' => [
                ['column' => 'status', 'value' => 'active'],
            ],
        ]));

    $filters = $response->inertiaProps('filters');
    expect(count($filters))->toBe(1);
    expect($filters[0]['column'])->toBe('status');
    expect($filters[0]['value'])->toBe('active');
});

it('ignores invalid filter columns', function () {
    Employee::create([
        'first_name' => 'Safe',
        'last_name' => 'One',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branchA->id,
        'current_daily_rate' => 500,
    ]);

    $response = $this->actingAs($this->adminA)
        ->get(route('payroll.employees.index', [
            'filters' => [
                ['column' => 'invalid_column', 'value' => 'anything'],
            ],
        ]));

    $data = $response->inertiaProps('employees')['data'];
    expect(count($data))->toBe(1);
});
