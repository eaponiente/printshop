<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\LeaveRequest;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
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

it('rejects a duplicate leave for the same date with a friendly error', function () {
    $payload = [
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'First filing',
    ];

    $this->actingAs($this->staff)->post('/payroll/leave-requests', $payload)->assertRedirect();

    // Re-filing the same date must fail gracefully (validation error on `date`),
    // not throw the DB unique-constraint exception, and not create a second row.
    $response = $this->actingAs($this->staff)
        ->post('/payroll/leave-requests', [...$payload, 'reason' => 'Second filing']);

    $response->assertSessionHasErrors('date');
    expect(LeaveRequest::count())->toBe(1);
});

it('superadmin can reset leave balances between Jan 1-15', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10'));

    $this->employee->update([
        'paid_leave_balance' => 2,
        'default_paid_leave_days' => 5,
    ]);

    $response = $this->actingAs($this->superadmin)
        ->post('/payroll/leave-requests/reset');

    $response->assertRedirect();

    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(5.0);

    Carbon::setTestNow();
});

it('blocks leave reset outside Jan 1-15', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-10'));

    $this->employee->update(['paid_leave_balance' => 2]);

    $response = $this->actingAs($this->superadmin)
        ->post('/payroll/leave-requests/reset');

    $response->assertSessionHasErrors('error');

    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(2.0);

    Carbon::setTestNow();
});

it('blocks non-superadmin from resetting leaves', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-10'));

    $response = $this->actingAs($this->admin)
        ->post('/payroll/leave-requests/reset');

    $response->assertForbidden();

    Carbon::setTestNow();
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

it('deletes an approved paid leave, refunds the balance and clears the leave day', function () {
    $this->employee->update(['paid_leave_balance' => 4]);

    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Test',
        'status' => 'approved',
    ]);

    // The approved leave wrote leave data onto the sheet.
    app(AttendanceService::class)->processDailyAttendance($this->employee, '2026-06-15');

    $response = $this->actingAs($this->admin)
        ->delete("/payroll/leave-requests/{$leave->id}");

    $response->assertRedirect();

    expect(LeaveRequest::find($leave->id))->toBeNull();

    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(5.0);

    // Attendance for the date is recomputed with no leave (no punches -> absent).
    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', '2026-06-15')
        ->first();
    expect($sheet->leave_type)->toBeNull();
    expect($sheet->absence_type)->not->toBe('approved_leave');
});

it('recomputes the day from real punches when a worked-through leave is deleted', function () {
    // Employee was on approved paid leave but actually came in and worked a full day.
    $this->employee->update(['paid_leave_balance' => 4]);

    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Changed mind, worked',
        'status' => 'approved',
    ]);

    foreach ([
        ['08:00', PunchType::IN],
        ['12:00', PunchType::LUNCH_OUT],
        ['13:00', PunchType::LUNCH_IN],
        ['17:00', PunchType::OUT],
    ] as [$time, $type]) {
        TimeLog::create([
            'employee_id' => $this->employee->id,
            'timestamp' => Carbon::parse("2026-06-15 {$time}"),
            'type' => $type,
            'source' => PunchSource::SELF_SERVICE,
        ]);
    }

    // While the leave is active it overrides the punches (leave day, no worked hours).
    $leaveSheet = app(AttendanceService::class)->processDailyAttendance($this->employee, '2026-06-15');
    expect($leaveSheet->leave_type)->toBe('vacation');
    expect((float) $leaveSheet->hours_worked)->toBe(0.0);

    $response = $this->actingAs($this->admin)
        ->delete("/payroll/leave-requests/{$leave->id}");

    $response->assertRedirect();

    // Punches are untouched by the delete.
    expect(TimeLog::where('employee_id', $this->employee->id)->count())->toBe(4);

    // The day now reflects the actual work, not the flat leave rate.
    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', '2026-06-15')
        ->first();
    expect($sheet->leave_type)->toBeNull();
    expect($sheet->absence_type)->not->toBe('approved_leave');
    expect($sheet->is_present)->toBeTrue();
    expect((float) $sheet->hours_worked)->toBeGreaterThan(0.0);
    expect((float) $sheet->daily_wage)->toBe(510.0);

    // Balance refunded since it was approved + paid.
    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(5.0);
});

it('does not change balance when an approved unpaid leave is deleted', function () {
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

    $this->actingAs($this->admin)
        ->delete("/payroll/leave-requests/{$leave->id}")
        ->assertRedirect();

    expect(LeaveRequest::find($leave->id))->toBeNull();

    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(3.0);
});

it('deletes a pending leave without touching the balance', function () {
    $this->employee->update(['paid_leave_balance' => 3]);

    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => false,
        'reason' => 'Test',
        'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->delete("/payroll/leave-requests/{$leave->id}")
        ->assertRedirect();

    expect(LeaveRequest::find($leave->id))->toBeNull();

    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(3.0);
});

it('blocks deleting an approved leave whose attendance sheet is locked', function () {
    $this->employee->update(['paid_leave_balance' => 4]);

    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Test',
        'status' => 'approved',
    ]);

    AttendanceSheet::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'schedule_start_time' => '08:00',
        'schedule_end_time' => '17:00',
        'daily_rate' => 510,
        'daily_wage' => 510,
        'is_present' => true,
        'locked_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->delete("/payroll/leave-requests/{$leave->id}");

    $response->assertSessionHasErrors('error');

    // Nothing changed.
    expect(LeaveRequest::find($leave->id))->not->toBeNull();
    $this->employee->refresh();
    expect((float) $this->employee->paid_leave_balance)->toBe(4.0);
});

it('forbids staff from deleting leaves', function () {
    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Test',
        'status' => 'approved',
    ]);

    $this->actingAs($this->staff)
        ->delete("/payroll/leave-requests/{$leave->id}")
        ->assertForbidden();

    expect(LeaveRequest::find($leave->id))->not->toBeNull();
});

it('rejects deleting a denied leave', function () {
    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => false,
        'reason' => 'Test',
        'status' => 'denied',
    ]);

    $response = $this->actingAs($this->admin)
        ->delete("/payroll/leave-requests/{$leave->id}");

    $response->assertSessionHasErrors('error');
    expect(LeaveRequest::find($leave->id))->not->toBeNull();
});

it('allows re-requesting a leave for the same employee and date after deletion', function () {
    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'First request',
        'status' => 'approved',
    ]);

    $this->actingAs($this->admin)
        ->delete("/payroll/leave-requests/{$leave->id}")
        ->assertRedirect();

    expect(LeaveRequest::where('employee_id', $this->employee->id)->where('date', '2026-06-15')->count())->toBe(0);

    // Hard delete frees the unique(employee_id, date) slot, so the same day can be requested again.
    $response = $this->actingAs($this->staff)
        ->post('/payroll/leave-requests', [
            'date' => '2026-06-15',
            'leave_type' => 'sick',
            'duration' => 'full_day',
            'is_paid' => true,
            'reason' => 'Second request',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = LeaveRequest::where('employee_id', $this->employee->id)
        ->where('date', '2026-06-15')
        ->get();
    expect($fresh)->toHaveCount(1);
    expect($fresh->first()->status)->toBe('pending');
    expect($fresh->first()->leave_type)->toBe('sick');
});

it('lets a superadmin delete any leave, including one in another branch', function () {
    $otherBranch = Branch::factory()->create(['name' => 'Other Branch']);
    $otherEmployee = Employee::create([
        'first_name' => 'Other',
        'last_name' => 'Worker',
        'branch_id' => $otherBranch->id,
        'current_daily_rate' => 510,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
        'position' => 'regular',
        'default_paid_leave_days' => 5,
        'paid_leave_balance' => 4,
    ]);

    $ownBranchLeave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Test',
        'status' => 'approved',
    ]);

    $otherBranchLeave = LeaveRequest::create([
        'employee_id' => $otherEmployee->id,
        'date' => '2026-06-16',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Test',
        'status' => 'approved',
    ]);

    $this->actingAs($this->superadmin)
        ->delete("/payroll/leave-requests/{$ownBranchLeave->id}")
        ->assertRedirect();
    expect(LeaveRequest::find($ownBranchLeave->id))->toBeNull();

    $this->actingAs($this->superadmin)
        ->delete("/payroll/leave-requests/{$otherBranchLeave->id}")
        ->assertRedirect();
    expect(LeaveRequest::find($otherBranchLeave->id))->toBeNull();
});

it('lets an admin delete their own leave and any staff leave in their branch', function () {
    // Give the admin their own employee record in the branch.
    $adminEmployee = Employee::create([
        'first_name' => 'Branch',
        'last_name' => 'Admin',
        'branch_id' => $this->branch->id,
        'current_daily_rate' => 510,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
        'position' => 'regular',
        'default_paid_leave_days' => 5,
        'paid_leave_balance' => 5,
    ]);
    $this->admin->update(['employee_id' => $adminEmployee->id]);

    $ownLeave = LeaveRequest::create([
        'employee_id' => $adminEmployee->id,
        'date' => '2026-06-15',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Admin own leave',
        'status' => 'approved',
    ]);

    $staffLeave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => '2026-06-16',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Staff leave',
        'status' => 'approved',
    ]);

    // Own leave.
    $this->actingAs($this->admin)
        ->delete("/payroll/leave-requests/{$ownLeave->id}")
        ->assertRedirect();
    expect(LeaveRequest::find($ownLeave->id))->toBeNull();

    // Staff leave in the same branch.
    $this->actingAs($this->admin)
        ->delete("/payroll/leave-requests/{$staffLeave->id}")
        ->assertRedirect();
    expect(LeaveRequest::find($staffLeave->id))->toBeNull();
});
