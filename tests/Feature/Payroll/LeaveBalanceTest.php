<?php

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\LeaveRequest;
use App\Models\User;
use Payroll\Attendance\Services\AttendanceService;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => 'Test Branch']);

    $this->superadmin = User::factory()->create([
        'branch_id' => null,
        'role' => 'superadmin',
    ]);

    $this->admin = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'admin',
        'employee_id' => null,
    ]);

    $this->staff = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role' => 'staff',
    ]);

    $this->employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'branch_id' => $this->branch->id,
        'current_daily_rate' => 510,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
        'position' => 'regular',
        'default_paid_leave_days' => 5,
        'paid_leave_balance' => 5,
    ]);

    EmployeeSchedule::create([
        'employee_id' => $this->employee->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0, 6],
        'effective_from' => now()->subMonth()->toDateString(),
    ]);

    $this->staff->update(['employee_id' => $this->employee->id]);
});

it('deducts leave balance when approving and employee has remaining leaves', function () {
    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => false,
        'reason' => 'Test',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin)
        ->post("/payroll/leave-requests/{$leave->id}/approve");

    $response->assertRedirect();

    $leave->refresh();
    expect($leave->status)->toBe('approved');
    expect($leave->is_paid)->toBeTrue();

    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(4.0);
});

it('marks leave unpaid when employee has no balance', function () {
    $this->employee->update(['paid_leave_balance' => 0]);

    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => false,
        'reason' => 'Test',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin)
        ->post("/payroll/leave-requests/{$leave->id}/approve");

    $response->assertRedirect();

    $leave->refresh();
    expect($leave->status)->toBe('approved');
    expect($leave->is_paid)->toBeFalse();

    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(0.0);
});

it('restores leave balance when paid leave is denied', function () {
    $this->employee->update(['paid_leave_balance' => 3]);

    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Test',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($this->admin)
        ->post("/payroll/leave-requests/{$leave->id}/deny");

    $response->assertRedirect();

    $leave->refresh();
    expect($leave->status)->toBe('denied');
    expect($leave->is_paid)->toBeFalse();

    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(4.0);
});

it('does not restore balance when unpaid leave is denied', function () {
    $this->employee->update(['paid_leave_balance' => 3]);

    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => false,
        'reason' => 'Test',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($this->admin)
        ->post("/payroll/leave-requests/{$leave->id}/deny");

    $response->assertRedirect();

    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(3.0);
});

it('rejects half-day leave duration in store', function () {
    $response = $this->actingAs($this->staff)
        ->post('/payroll/leave-requests', [
            'date' => '2026-06-15',
            'leave_type' => 'vacation',
            'duration' => 'half_day_afternoon',
            'is_paid' => true,
            'reason' => 'Test half day',
        ]);

    $response->assertSessionHasErrors('duration');
});

it('allows full-day leave submission', function () {
    $response = $this->actingAs($this->staff)
        ->post('/payroll/leave-requests', [
            'date' => '2026-06-15',
            'leave_type' => 'vacation',
            'duration' => 'full_day',
            'is_paid' => true,
            'reason' => 'Test full day',
        ]);

    $response->assertRedirect();

    expect(LeaveRequest::count())->toBe(1);
});

it('superadmin can reset leave balances between Jan 1-15', function () {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-01-10'));

    $this->employee->update([
        'paid_leave_balance' => 2,
        'default_paid_leave_days' => 5,
    ]);

    $response = $this->actingAs($this->superadmin)
        ->post('/payroll/leave-requests/reset');

    $response->assertRedirect();

    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(5.0);

    Carbon\Carbon::setTestNow();
});

it('blocks leave reset outside Jan 1-15', function () {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-03-10'));

    $this->employee->update(['paid_leave_balance' => 2]);

    $response = $this->actingAs($this->superadmin)
        ->post('/payroll/leave-requests/reset');

    $response->assertSessionHasErrors('error');

    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(2.0);

    Carbon\Carbon::setTestNow();
});

it('blocks non-superadmin from resetting leaves', function () {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-01-10'));

    $response = $this->actingAs($this->admin)
        ->post('/payroll/leave-requests/reset');

    $response->assertForbidden();

    Carbon\Carbon::setTestNow();
});

it('sets leave defaults on employee creation', function () {
    $employee = Employee::create([
        'first_name' => 'New',
        'last_name' => 'Guy',
        'branch_id' => $this->branch->id,
        'current_daily_rate' => 510,
        'status' => 'active',
        'hire_date' => now()->toDateString(),
        'position' => 'regular',
        'default_paid_leave_days' => 5,
        'paid_leave_balance' => 5,
    ]);

    expect((float) $employee->default_paid_leave_days)->toBe(5.0);
    expect((float) $employee->paid_leave_balance)->toBe(5.0);
});

it('attendance service marks leave as paid when is_paid is true', function () {
    LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Paid leave',
        'status' => 'approved',
    ]);

    $sheet = app(AttendanceService::class)->processDailyAttendance(
        $this->employee,
        '2026-06-15',
    );

    expect((float) $sheet->daily_wage)->toBe(510.0);
    expect($sheet->leave_is_paid)->toBeTrue();
});

it('attendance service marks leave as unpaid when is_paid is false', function () {
    LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => false,
        'reason' => 'Unpaid leave',
        'status' => 'approved',
    ]);

    $sheet = app(AttendanceService::class)->processDailyAttendance(
        $this->employee,
        '2026-06-15',
    );

    expect((float) $sheet->daily_wage)->toBe(0.0);
    expect($sheet->leave_is_paid)->toBeFalse();
});
