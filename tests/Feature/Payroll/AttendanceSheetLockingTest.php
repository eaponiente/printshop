<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\CorrectionRequest;
use App\Models\Payroll\CorrectionRequestItem;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\Fine;
use App\Models\Payroll\LeaveRequest;
use App\Models\Payroll\OvertimeRequest;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\Salary;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use Payroll\Attendance\Enums\PayrollPeriodStatus;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\PayrollPeriodService;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => 'Test Branch']);

    $this->superadmin = User::factory()->create([
        'role' => 'superadmin',
        'branch_id' => null,
    ]);

    $this->admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branch->id,
    ]);

    $this->staff = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branch->id,
    ]);

    $this->employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'branch_id' => $this->branch->id,
        'hire_date' => '2026-01-01',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 510,
    ]);

    Salary::createForEmployee($this->employee, 510, '2026-01-01', 'Initial');

    EmployeeSchedule::create([
        'employee_id' => $this->employee->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0, 6],
        'effective_from' => '2026-05-01',
        'is_active' => true,
    ]);

    // Link staff user to employee
    $this->staff->update(['employee_id' => $this->employee->id]);

    $this->lockedDate = '2026-05-26';
    $this->unlockedDate = '2026-05-27';

    // Create attendance sheets for both dates
    AttendanceSheet::create([
        'employee_id' => $this->employee->id,
        'date' => $this->lockedDate,
        'schedule_start_time' => '08:00',
        'schedule_end_time' => '17:00',
        'daily_rate' => 510,
        'hours_worked' => 8,
        'daily_wage' => 510,
        'is_present' => true,
    ]);

    AttendanceSheet::create([
        'employee_id' => $this->employee->id,
        'date' => $this->unlockedDate,
        'schedule_start_time' => '08:00',
        'schedule_end_time' => '17:00',
        'daily_rate' => 510,
        'hours_worked' => 8,
        'daily_wage' => 510,
        'is_present' => true,
    ]);

    // Lock the lockedDate sheet by generating a payroll period
    $service = app(PayrollPeriodService::class);
    $service->generate($this->branch, '2026-05-25', '2026-05-30');

    // Verify the sheet is locked
    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $this->lockedDate)
        ->first();
    expect($sheet->locked_at)->not->toBeNull();

    // Verify the unlocked date sheet is also locked (since it's in the same period)
    // But we need an unlocked one for testing, so let's create another date outside the period
    $this->trulyUnlockedDate = '2026-06-02';
    AttendanceSheet::create([
        'employee_id' => $this->employee->id,
        'date' => $this->trulyUnlockedDate,
        'schedule_start_time' => '08:00',
        'schedule_end_time' => '17:00',
        'daily_rate' => 510,
        'hours_worked' => 8,
        'daily_wage' => 510,
        'is_present' => true,
    ]);
});

// ─────────── Time Logs ───────────

it('blocks self-service punches on locked dates', function () {
    Carbon::setTestNow(Carbon::parse($this->lockedDate.' 08:00:00'));

    $response = $this->actingAs($this->staff)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
        ]);

    $response->assertSessionHasErrors('error');
    expect(TimeLog::count())->toBe(0);

    Carbon::setTestNow();
});

it('allows punches on unlocked dates', function () {
    // Travel to the unlocked date
    Carbon::setTestNow(Carbon::parse($this->trulyUnlockedDate.' 08:00:00'));

    $response = $this->actingAs($this->staff)
        ->post(route('payroll.attendance.punch'), [
            'type' => 'in',
        ]);

    $response->assertRedirect();
    expect(TimeLog::count())->toBe(1);

    Carbon::setTestNow();
});

it('blocks manual time logs on locked dates', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('payroll.attendance.manual'), [
            'employee_id' => $this->employee->id,
            'type' => 'in',
            'timestamp' => $this->lockedDate.' 08:00:00',
            'note' => 'Test',
        ]);

    $response->assertSessionHasErrors('error');
    expect(TimeLog::count())->toBe(0);
});

it('allows manual time logs on unlocked dates', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('payroll.attendance.manual'), [
            'employee_id' => $this->employee->id,
            'type' => 'in',
            'timestamp' => $this->trulyUnlockedDate.' 08:00:00',
            'note' => 'Test',
        ]);

    $response->assertRedirect();
    expect(TimeLog::count())->toBe(1);
});

// ─────────── Fines ───────────

it('blocks fine creation on locked dates', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('payroll.fines.store'), [
            'employee_id' => $this->employee->id,
            'date' => $this->lockedDate,
            'fine_type' => 'No Uniform',
            'amount' => 20,
        ]);

    $response->assertSessionHasErrors('error');
    expect(Fine::count())->toBe(0);
});

it('allows fine creation on unlocked dates', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('payroll.fines.store'), [
            'employee_id' => $this->employee->id,
            'date' => $this->trulyUnlockedDate,
            'fine_type' => 'No Uniform',
            'amount' => 20,
        ]);

    $response->assertRedirect();
    expect(Fine::count())->toBe(1);
});

it('blocks fine removal on locked dates', function () {
    $fine = Fine::create([
        'employee_id' => $this->employee->id,
        'date' => $this->lockedDate,
        'fine_type' => 'No Uniform',
        'amount' => 20,
        'marked_by' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->delete(route('payroll.fines.destroy', $fine));

    $response->assertSessionHasErrors('error');
    expect(Fine::count())->toBe(1);
});

it('allows fine removal on unlocked dates', function () {
    $fine = Fine::create([
        'employee_id' => $this->employee->id,
        'date' => $this->trulyUnlockedDate,
        'fine_type' => 'No Uniform',
        'amount' => 20,
        'marked_by' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)
        ->delete(route('payroll.fines.destroy', $fine));

    $response->assertRedirect();
    expect(Fine::count())->toBe(0);
});

// ─────────── Correction Requests ───────────

it('blocks correction request submission on locked dates', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.corrections.store'), [
            'date' => $this->lockedDate,
            'correction_type' => 'missed_punch_in',
            'items' => [
                ['punch_type' => 'in', 'requested_time' => '08:00'],
            ],
            'reason' => 'Forgot to punch',
        ]);

    $response->assertSessionHasErrors('error');
    expect(CorrectionRequest::count())->toBe(0);
});

it('allows correction request submission on unlocked dates', function () {
    $response = $this->actingAs($this->staff)
        ->post(route('payroll.corrections.store'), [
            'date' => $this->trulyUnlockedDate,
            'correction_type' => 'missed_punch_in',
            'items' => [
                ['punch_type' => 'in', 'requested_time' => '08:00'],
            ],
            'reason' => 'Forgot to punch',
        ]);

    $response->assertRedirect();
    expect(CorrectionRequest::count())->toBe(1);
});

it('blocks correction request approval on locked dates', function () {
    $correction = CorrectionRequest::create([
        'employee_id' => $this->employee->id,
        'date' => $this->lockedDate,
        'correction_type' => 'missed_punch_in',
        'reason' => 'Forgot to punch',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('payroll.corrections.approve', $correction));

    $response->assertSessionHasErrors('error');
    expect(TimeLog::count())->toBe(0);
    expect($correction->fresh()->status)->toBe('pending');
});

it('allows correction request approval on unlocked dates', function () {
    $correction = CorrectionRequest::create([
        'employee_id' => $this->employee->id,
        'date' => $this->trulyUnlockedDate,
        'correction_type' => 'missed_punch_in',
        'reason' => 'Forgot to punch',
        'status' => 'pending',
    ]);

    CorrectionRequestItem::create([
        'correction_request_id' => $correction->id,
        'punch_type' => 'in',
        'requested_time' => $this->trulyUnlockedDate.' 08:00:00',
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('payroll.corrections.approve', $correction));

    $response->assertRedirect();
    expect(TimeLog::count())->toBe(1);
    expect($correction->fresh()->status)->toBe('approved');
});

// ─────────── Overtime Requests ───────────

it('blocks overtime request approval on locked dates', function () {
    $ot = OvertimeRequest::create([
        'employee_id' => $this->employee->id,
        'date' => $this->lockedDate,
        'start_time' => $this->lockedDate.' 17:00:00',
        'end_time' => $this->lockedDate.' 19:00:00',
        'shift_type' => 'regular_day',
        'reason' => 'Extra work',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('payroll.overtime.approve', $ot));

    $response->assertSessionHasErrors('error');
    expect($ot->fresh()->status)->toBe('pending');
});

it('allows overtime request approval on unlocked dates and recomputes attendance', function () {
    $ot = OvertimeRequest::create([
        'employee_id' => $this->employee->id,
        'date' => $this->trulyUnlockedDate,
        'start_time' => $this->trulyUnlockedDate.' 17:00:00',
        'end_time' => $this->trulyUnlockedDate.' 19:00:00',
        'shift_type' => 'regular_day',
        'reason' => 'Extra work',
        'status' => 'pending',
    ]);

    // Create a punch to establish the employee worked that day
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
        'timestamp' => $this->trulyUnlockedDate.' 08:00:00',
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
        'timestamp' => $this->trulyUnlockedDate.' 19:00:00',
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('payroll.overtime.approve', $ot));

    $response->assertRedirect();
    expect($ot->fresh()->status)->toBe('approved');

    // The attendance sheet should have been recomputed with OT
    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $this->trulyUnlockedDate)
        ->first();
    expect($sheet->overtime_minutes)->toBeGreaterThan(0);
});

// ─────────── Leave Requests ───────────

it('blocks leave request approval on locked dates', function () {
    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => $this->lockedDate,
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Personal',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('payroll.leaves.approve', $leave));

    $response->assertSessionHasErrors('error');
    expect($leave->fresh()->status)->toBe('pending');
});

it('allows leave request approval on unlocked dates and recomputes attendance', function () {
    $leave = LeaveRequest::create([
        'employee_id' => $this->employee->id,
        'date' => $this->trulyUnlockedDate,
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Personal',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('payroll.leaves.approve', $leave));

    $response->assertRedirect();
    expect($leave->fresh()->status)->toBe('approved');

    // The attendance sheet should have been recomputed with leave
    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $this->trulyUnlockedDate)
        ->first();
    expect($sheet->leave_type)->toBe('vacation');
});

// ─────────── Draft period deletion unlocks sheets ───────────

it('unlocks attendance sheets when draft payroll period is deleted', function () {
    $period = PayrollPeriod::where('branch_id', $this->branch->id)
        ->where('period_start', '2026-05-25')
        ->where('period_end', '2026-05-30')
        ->first();

    expect($period->status)->toBe(PayrollPeriodStatus::DRAFT);

    $service = app(PayrollPeriodService::class);
    $service->delete($period);

    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $this->lockedDate)
        ->first();
    expect($sheet->locked_at)->toBeNull();
});

it('unlocks attendance sheets when approved payroll period is voided', function () {
    $period = PayrollPeriod::where('branch_id', $this->branch->id)
        ->where('period_start', '2026-05-25')
        ->where('period_end', '2026-05-30')
        ->first();

    // Approve the period first
    $service = app(PayrollPeriodService::class);
    $service->approve($period, $this->superadmin->id);

    // Then void it
    $service->void($period);

    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $this->lockedDate)
        ->first();
    expect($sheet->locked_at)->toBeNull();
});
