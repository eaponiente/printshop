<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\OvertimeRequest;
use App\Models\Payroll\TimeLog;
use App\Models\User;
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

it('allows staff to submit overtime request with start and end time', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.overtime.store'), [
            'date' => now()->toDateString(),
            'start_time' => '17:00',
            'end_time' => '19:00',
            'reason' => 'Deadline rush',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $ot = OvertimeRequest::where('employee_id', $this->employee->id)->first();
    expect($ot)->not->toBeNull();
    expect($ot->getApprovedMinutes())->toBe(120);
    expect($ot->status)->toBe('pending');
});

it('rejects overtime with end_time before start_time', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.overtime.store'), [
            'date' => now()->toDateString(),
            'start_time' => '17:00',
            'end_time' => '15:00',
            'reason' => 'Invalid times',
        ]);

    $response->assertSessionHasErrors(['end_time']);
});

it('rejects overtime with missing fields', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.overtime.store'), [
            'date' => now()->toDateString(),
            'start_time' => '17:00',
            'reason' => 'No end time',
        ]);

    $response->assertSessionHasErrors(['end_time']);
});

it('resolves shift type from employee schedule', function () {
    EmployeeSchedule::create([
        'employee_id' => $this->employee->id,
        'start_time' => '2024-01-01 08:00:00',
        'end_time' => '2024-01-01 17:00:00',
        'rest_days' => [0, 6],
        'effective_from' => now()->subDays(7)->toDateString(),
    ]);

    $this->actingAs($this->staff)
        ->post(route('payroll.overtime.store'), [
            'date' => now()->toDateString(),
            'start_time' => '17:00',
            'end_time' => '19:00',
            'reason' => 'Testing shift type',
        ]);

    $ot = OvertimeRequest::where('employee_id', $this->employee->id)->first();
    expect($ot->shift_type)->toBe('regular_day');
});

it('allows admin to approve overtime request', function () {
    $ot = OvertimeRequest::create([
        'employee_id' => $this->employee->id,
        'date' => now()->toDateString(),
        'start_time' => now()->toDateString().' 17:00:00',
        'end_time' => now()->toDateString().' 19:00:00',
        'shift_type' => 'regular_day',
        'reason' => 'Test',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('payroll.overtime.approve', $ot));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $ot->refresh();
    expect($ot->status)->toBe('approved');
    expect($ot->approved_by)->toBe($this->admin->id);
    expect($ot->approved_at)->not->toBeNull();
});

it('allows admin to deny overtime request', function () {
    $ot = OvertimeRequest::create([
        'employee_id' => $this->employee->id,
        'date' => now()->toDateString(),
        'start_time' => now()->toDateString().' 17:00:00',
        'end_time' => now()->toDateString().' 19:00:00',
        'shift_type' => 'regular_day',
        'reason' => 'Test',
        'status' => 'pending',
    ]);

    $this->actingAs($this->admin)
        ->post(route('payroll.overtime.deny', $ot));

    $ot->refresh();
    expect($ot->status)->toBe('denied');
});

it('uses approved overtime minutes in attendance sheet', function () {
    $date = now()->toDateString();

    EmployeeSchedule::create([
        'employee_id' => $this->employee->id,
        'start_time' => '2024-01-01 08:00:00',
        'end_time' => '2024-01-01 17:00:00',
        'rest_days' => [0, 6],
        'effective_from' => now()->subDays(7)->toDateString(),
    ]);

    OvertimeRequest::create([
        'employee_id' => $this->employee->id,
        'date' => $date,
        'start_time' => $date.' 17:00:00',
        'end_time' => $date.' 19:00:00',
        'shift_type' => 'regular_day',
        'reason' => 'Production deadline',
        'status' => 'approved',
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
        'timestamp' => $date.' 08:00:00',
    ]);

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'type' => PunchType::LUNCH_OUT,
        'source' => PunchSource::SELF_SERVICE,
        'timestamp' => $date.' 12:00:00',
    ]);

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'type' => PunchType::LUNCH_IN,
        'source' => PunchSource::SELF_SERVICE,
        'timestamp' => $date.' 13:00:00',
    ]);

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
        'timestamp' => $date.' 19:00:00',
    ]);

    app(AttendanceService::class)->processDailyAttendance(
        $this->employee,
        $date,
    );

    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $date)
        ->first();

    expect($sheet)->not->toBeNull();
    // Under the punch-driven OT model, an approved OvertimeRequest with no
    // OT punches contributes its full approved window (17:00–19:00 = 120 min).
    expect($sheet->overtime_minutes)->toBe(120);
    expect($sheet->overtime_pay)->toBeGreaterThan(0);
});

it('caps overtime at approved amount even if employee stays longer', function () {
    $date = now()->toDateString();

    EmployeeSchedule::create([
        'employee_id' => $this->employee->id,
        'start_time' => '2024-01-01 08:00:00',
        'end_time' => '2024-01-01 17:00:00',
        'rest_days' => [0, 6],
        'effective_from' => now()->subDays(7)->toDateString(),
    ]);

    OvertimeRequest::create([
        'employee_id' => $this->employee->id,
        'date' => $date,
        'start_time' => $date.' 17:00:00',
        'end_time' => $date.' 19:00:00',
        'shift_type' => 'regular_day',
        'reason' => 'Production deadline',
        'status' => 'approved',
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
        'timestamp' => $date.' 08:00:00',
    ]);

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'type' => PunchType::LUNCH_OUT,
        'source' => PunchSource::SELF_SERVICE,
        'timestamp' => $date.' 12:00:00',
    ]);

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'type' => PunchType::LUNCH_IN,
        'source' => PunchSource::SELF_SERVICE,
        'timestamp' => $date.' 13:00:00',
    ]);

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
        'timestamp' => $date.' 21:00:00',
    ]);

    app(AttendanceService::class)->processDailyAttendance(
        $this->employee,
        $date,
    );

    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $date)
        ->first();

    expect($sheet)->not->toBeNull();
    expect($sheet->overtime_minutes)->toBe(120);
});

it('denies staff from approving overtime', function () {
    $ot = OvertimeRequest::create([
        'employee_id' => $this->employee->id,
        'date' => now()->toDateString(),
        'start_time' => now()->toDateString().' 17:00:00',
        'end_time' => now()->toDateString().' 19:00:00',
        'shift_type' => 'regular_day',
        'reason' => 'Test',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->staff)
        ->post(route('payroll.overtime.approve', $ot));

    $response->assertForbidden();
});
