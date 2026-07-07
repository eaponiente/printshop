<?php

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => 'Branch A']);

    $this->admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branch->id,
    ]);
});

function makeStaffWithEmployeeStatus(Branch $branch, string $name, string $status): User
{
    $employee = Employee::create([
        'first_name' => $name,
        'last_name' => 'Test',
        'branch_id' => $branch->id,
        'hire_date' => '2026-01-05',
        'position' => 'regular',
        'status' => $status,
        'current_daily_rate' => 500,
    ]);

    return User::factory()->create([
        'first_name' => $name,
        'role' => 'staff',
        'branch_id' => $branch->id,
        'employee_id' => $employee->id,
    ]);
}

it('excludes deactivated staff from the sublimations user filter and assignment list', function () {
    $active = makeStaffWithEmployeeStatus($this->branch, 'Active', 'active');
    $inactive = makeStaffWithEmployeeStatus($this->branch, 'Inactive', 'inactive');
    $terminated = makeStaffWithEmployeeStatus($this->branch, 'Terminated', 'terminated');
    $noEmployee = User::factory()->create([
        'first_name' => 'NoEmployee',
        'role' => 'staff',
        'branch_id' => $this->branch->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('sublimations.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($active, $inactive, $terminated, $noEmployee) {
            $ids = collect($page->toArray()['props']['users'])->pluck('id');

            expect($ids)->toContain($active->id);
            expect($ids)->toContain($noEmployee->id);
            expect($ids)->not->toContain($inactive->id);
            expect($ids)->not->toContain($terminated->id);
        });
});

it('excludes a deactivated employees linked user even after the employee is soft-deleted', function () {
    $employee = Employee::create([
        'first_name' => 'Gone',
        'last_name' => 'Test',
        'branch_id' => $this->branch->id,
        'hire_date' => '2026-01-05',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 500,
    ]);

    $user = User::factory()->create([
        'first_name' => 'Gone',
        'role' => 'staff',
        'branch_id' => $this->branch->id,
        'employee_id' => $employee->id,
    ]);

    $employee->delete();

    $this->actingAs($this->admin)
        ->get(route('sublimations.index'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($user) {
            $ids = collect($page->toArray()['props']['users'])->pluck('id');

            expect($ids)->not->toContain($user->id);
        });
});
