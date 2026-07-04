<?php

use App\Models\Branch;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\Employee;
use App\Models\User;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => 'Test Branch']);

    $this->superadmin = User::factory()->create([
        'branch_id' => null,
        'role' => 'superadmin',
    ]);

    $this->admin = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'admin',
    ]);

    $this->staff = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'staff',
    ]);

    $this->employee = Employee::create([
        'first_name' => 'Staff',
        'last_name' => 'Member',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branch->id,
        'current_daily_rate' => 500,
    ]);

    $this->staff->update(['employee_id' => $this->employee->id]);
});

it('allows creating a second cash advance while the first is still unpaid', function () {
    $this->actingAs($this->admin)
        ->post(route('payroll.cash-advances.store'), [
            'employee_id' => $this->employee->id,
            'amount' => 1000,
            'reason' => 'First advance',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->post(route('payroll.cash-advances.store'), [
            'employee_id' => $this->employee->id,
            'amount' => 500,
            'reason' => 'Second advance',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(CashAdvance::where('employee_id', $this->employee->id)->count())->toBe(2);
    expect(CashAdvance::where('employee_id', $this->employee->id)->where('status', 'approved')->count())->toBe(2);
});

it('lets multiple cash advances coexist for different employees at the same branch', function () {
    $otherEmployee = Employee::create([
        'first_name' => 'Another',
        'last_name' => 'Employee',
        'hire_date' => now()->toDateString(),
        'branch_id' => $this->branch->id,
        'current_daily_rate' => 500,
    ]);

    $this->actingAs($this->admin)
        ->post(route('payroll.cash-advances.store'), [
            'employee_id' => $this->employee->id,
            'amount' => 1000,
            'reason' => 'For employee A',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->post(route('payroll.cash-advances.store'), [
            'employee_id' => $otherEmployee->id,
            'amount' => 2000,
            'reason' => 'For employee B',
        ])
        ->assertSessionHasNoErrors();

    expect(CashAdvance::count())->toBe(2);
});

it('denies staff from creating a cash advance', function () {
    $this->actingAs($this->staff)
        ->post(route('payroll.cash-advances.store'), [
            'employee_id' => $this->employee->id,
            'amount' => 1000,
            'reason' => 'Should be blocked',
        ])
        ->assertForbidden();
});

it('denies admin from creating a cash advance for another branch', function () {
    $otherBranch = Branch::factory()->create();
    $otherEmployee = Employee::create([
        'first_name' => 'Other',
        'last_name' => 'Branch',
        'hire_date' => now()->toDateString(),
        'branch_id' => $otherBranch->id,
        'current_daily_rate' => 500,
    ]);

    $this->actingAs($this->admin)
        ->post(route('payroll.cash-advances.store'), [
            'employee_id' => $otherEmployee->id,
            'amount' => 1000,
            'reason' => 'Cross-branch attempt',
        ])
        ->assertForbidden();
});
