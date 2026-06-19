<?php

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Salary;
use App\Models\User;
use Payroll\Employee\Enums\EmployeeStatus;

function createActiveEmployeeUser(): array
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $employee = Employee::create([
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'branch_id' => $branch->id,
        'employee_number' => Employee::generateEmployeeNumber(),
        'hire_date' => now()->toDateString(),
        'position' => 'regular',
        'status' => EmployeeStatus::ACTIVE,
        'current_daily_rate' => 1000,
    ]);

    Salary::createForEmployee($employee, 1000, now()->toDateString(), 'Initial salary');

    $user->update(['employee_id' => $employee->id]);

    return ['user' => $user, 'employee' => $employee, 'branch' => $branch];
}

it('sets remember token when logging in with remember me', function () {
    $data = createActiveEmployeeUser();
    $user = $data['user'];

    $this->get(route('dashboard'));

    $response = $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $response->assertRedirect(route('dashboard'));

    expect($user->fresh()->remember_token)->not->toBeNull();
});

it('allows active employee user to login and access protected routes', function () {
    $data = createActiveEmployeeUser();
    $user = $data['user'];

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('blocks deactivated employee user from accessing protected routes', function () {
    $data = createActiveEmployeeUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $employee->update(['status' => EmployeeStatus::INACTIVE]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('home'));
});

it('blocks soft-deleted employee user from accessing protected routes', function () {
    $data = createActiveEmployeeUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    $employee->delete();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('home'));
});

it('blocked user sees error message on login page', function () {
    $data = createActiveEmployeeUser();
    $user = $data['user'];
    $employee = $data['employee'];

    $employee->update(['status' => EmployeeStatus::INACTIVE]);

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertRedirect(route('home'));

    $followed = $this->get(route('home'));

    $followed->assertSee('Your account has been deactivated');
});
