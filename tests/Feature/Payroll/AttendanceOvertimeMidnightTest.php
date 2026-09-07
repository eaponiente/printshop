<?php

use App\Models\Branch;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\TimeLog;
use App\Services\Payroll\PayrollSettingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;

beforeEach(function () {
    Cache::flush();

    $branch = Branch::factory()->create(['name' => 'Test Branch']);

    $this->employee = Employee::create([
        'first_name' => 'Mid',
        'last_name' => 'Night',
        'branch_id' => $branch->id,
        'current_daily_rate' => 525,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
        'position' => 'regular',
    ]);

    EmployeeSchedule::create([
        'employee_id' => $this->employee->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0, 6],
        'effective_from' => now()->subMonth()->toDateString(),
    ]);

    $this->service = app(AttendanceService::class);
    $this->hourlyRate = 525 / 8;
});

function otPunch(Employee $employee, string $date, string $time, PunchType $type): void
{
    TimeLog::create([
        'employee_id' => $employee->id,
        'timestamp' => Carbon::parse("{$date} {$time}"),
        'type' => $type,
        'source' => PunchSource::SELF_SERVICE,
    ]);
}

it('rolls a midnight-crossing overtime span forward instead of measuring it backwards', function () {
    $date = '2026-08-10';

    otPunch($this->employee, $date, '08:02', PunchType::IN);
    otPunch($this->employee, $date, '11:14', PunchType::LUNCH_OUT);
    otPunch($this->employee, $date, '11:46', PunchType::LUNCH_IN);
    otPunch($this->employee, $date, '17:32', PunchType::OUT);
    otPunch($this->employee, $date, '19:58', PunchType::OVERTIME_IN);
    // Overtime-out is stamped with the shift's own date even though it's really 01:15 the next day.
    otPunch($this->employee, $date, '01:15', PunchType::OVERTIME_OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->overtime_minutes)->toBe(317)
        ->and((float) $sheet->overtime_multiplier)->toBe(1.25)
        ->and((float) $sheet->overtime_pay)->toBe(433.40)
        ->and((bool) $sheet->is_incomplete)->toBeFalse();

    // Sanity check against the canonical daily_wage formula: base pay minus
    // late deduction plus overtime pay (no undertime, fines, or holiday pay here).
    expect((float) $sheet->daily_wage)
        ->toBe(round(525 - (float) $sheet->late_deduction + (float) $sheet->overtime_pay, 2));
});

it('still computes an ordinary non-crossing overtime span correctly (regression guard)', function () {
    $date = '2026-08-10';

    otPunch($this->employee, $date, '08:00', PunchType::IN);
    otPunch($this->employee, $date, '12:00', PunchType::LUNCH_OUT);
    otPunch($this->employee, $date, '13:00', PunchType::LUNCH_IN);
    otPunch($this->employee, $date, '17:00', PunchType::OUT);
    otPunch($this->employee, $date, '18:00', PunchType::OVERTIME_IN);
    otPunch($this->employee, $date, '20:00', PunchType::OVERTIME_OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->overtime_minutes)->toBe(120)
        ->and((float) $sheet->overtime_multiplier)->toBe(1.25)
        ->and((float) $sheet->overtime_pay)->toBe(round((120 / 60) * $this->hourlyRate * 1.25, 2))
        ->and((bool) $sheet->is_incomplete)->toBeFalse();
});

it('withholds pay and flags the sheet when an overtime span exceeds the configured cap', function () {
    $date = '2026-08-10';

    otPunch($this->employee, $date, '08:00', PunchType::IN);
    otPunch($this->employee, $date, '12:00', PunchType::LUNCH_OUT);
    otPunch($this->employee, $date, '13:00', PunchType::LUNCH_IN);
    otPunch($this->employee, $date, '17:00', PunchType::OUT);
    otPunch($this->employee, $date, '06:00', PunchType::OVERTIME_IN);
    // Rolls forward to next-day 05:00 -> 23h00m = 1380 minutes, well past the 720-minute cap.
    otPunch($this->employee, $date, '05:00', PunchType::OVERTIME_OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->overtime_minutes)->toBe(1380)
        ->and((float) $sheet->overtime_pay)->toBe(0.0)
        ->and($sheet->overtime_multiplier)->toBeNull()
        ->and((bool) $sheet->is_incomplete)->toBeTrue()
        ->and($sheet->incomplete_reason)->toContain('720');
});

it('keeps an earlier incomplete reason instead of overwriting it with the overtime-cap reason', function () {
    $date = '2026-08-10';

    otPunch($this->employee, $date, '08:00', PunchType::IN);
    otPunch($this->employee, $date, '12:00', PunchType::LUNCH_OUT);
    // Lunch-in punch is missing.
    otPunch($this->employee, $date, '17:00', PunchType::OUT);
    otPunch($this->employee, $date, '06:00', PunchType::OVERTIME_IN);
    otPunch($this->employee, $date, '05:00', PunchType::OVERTIME_OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((bool) $sheet->is_incomplete)->toBeTrue()
        ->and($sheet->incomplete_reason)->toBe('Lunch return punch missing')
        ->and((float) $sheet->overtime_pay)->toBe(0.0);
});

it('pays OT premium only for an OT-only weekday call-in that crosses midnight', function () {
    // 2026-08-10 is a Monday — an ordinary working day for this employee
    // (rest days are Sunday/Saturday). No regular in/out/lunch punches at
    // all, only the overtime pair — a worked rest day is paid exactly like a
    // regular day, so this must behave identically to the rest-day version
    // in RestDayPayTest.php.
    $date = '2026-08-10';
    $nextDate = '2026-08-11';

    otPunch($this->employee, $date, '19:44', PunchType::OVERTIME_IN);
    otPunch($this->employee, $nextDate, '00:20', PunchType::OVERTIME_OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((bool) $sheet->is_rest_day)->toBeFalse();
    expect((int) $sheet->overtime_minutes)->toBe(276)
        ->and((float) $sheet->overtime_multiplier)->toBe(1.25)
        ->and((float) $sheet->hours_worked)->toBe(0.0)
        ->and((float) $sheet->overtime_pay)->toBe(round((276 / 60) * $this->hourlyRate * 1.25, 2))
        ->and((float) $sheet->daily_wage)->toBe(round((276 / 60) * $this->hourlyRate * 1.25, 2))
        ->and((bool) $sheet->is_incomplete)->toBeFalse();
});

it('does not swallow a next-day punch stamped at or after the 06:00 cutoff', function () {
    $date = '2026-08-10';
    $nextDate = '2026-08-11';

    otPunch($this->employee, $date, '23:00', PunchType::OVERTIME_IN);
    // Stamped exactly at the cutoff — must NOT be treated as this OT-in's close.
    otPunch($this->employee, $nextDate, '06:00', PunchType::OVERTIME_OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->overtime_minutes)->toBe(0)
        ->and((float) $sheet->overtime_pay)->toBe(0.0)
        ->and((bool) $sheet->is_incomplete)->toBeTrue()
        ->and($sheet->incomplete_reason)->toBe('Overtime punch-out missing');
});

it('still pays the full flat daily rate when the punch-in is missing but other regular punches exist (scope-guard regression)', function () {
    // No in-punch and no overtime punches, but lunch AND punch-out ARE
    // recorded. This is NOT an OT-only day (there are regular punches), so
    // the OT-only-day zeroing introduced by this change must not apply here
    // — the employee still receives the full flat daily rate exactly as
    // before. (Lunch punches are both present so the separate no-break-fine
    // rule — which only requires a punch-out, not a punch-in — stays inert
    // and doesn't muddy this assertion.)
    $date = '2026-08-10';

    otPunch($this->employee, $date, '12:00', PunchType::LUNCH_OUT);
    otPunch($this->employee, $date, '13:00', PunchType::LUNCH_IN);
    otPunch($this->employee, $date, '17:00', PunchType::OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((float) $sheet->hours_worked)->toBe(0.0)
        ->and((int) $sheet->overtime_minutes)->toBe(0)
        ->and((float) $sheet->fine_deduction)->toBe(0.0)
        ->and((float) $sheet->daily_wage)->toBe(525.0)
        ->and((bool) $sheet->is_incomplete)->toBeTrue()
        ->and($sheet->incomplete_reason)->toBe('No punch-in recorded');
});

it('resolves a chain of two consecutive midnight-crossing overtime nights without leaking an orphan onto the third day', function () {
    // Regression for a defect where the exclusion and lookahead predicates
    // disagreed the moment yesterday's own overtime-out was itself a
    // carry-over from the day before: a raw "does yesterday have ANY
    // OVERTIME_OUT" check found 08-11's 01:00 punch when processing 08-12,
    // but that punch actually belongs to 08-10's shift, not 08-11's.
    $d1 = '2026-08-10'; // Monday
    $d2 = '2026-08-11'; // Tuesday
    $d3 = '2026-08-12'; // Wednesday

    otPunch($this->employee, $d1, '22:00', PunchType::OVERTIME_IN);
    otPunch($this->employee, $d2, '01:00', PunchType::OVERTIME_OUT); // closes D1: 22:00->01:00 = 180 min
    otPunch($this->employee, $d2, '22:00', PunchType::OVERTIME_IN);
    otPunch($this->employee, $d3, '02:00', PunchType::OVERTIME_OUT); // closes D2: 22:00->02:00 = 240 min

    $sheet1 = $this->service->processDailyAttendance($this->employee, $d1);
    $sheet2 = $this->service->processDailyAttendance($this->employee, $d2);
    $sheet3 = $this->service->processDailyAttendance($this->employee, $d3);

    expect((int) $sheet1->overtime_minutes)->toBe(180)
        ->and((bool) $sheet1->is_incomplete)->toBeFalse();

    expect((int) $sheet2->overtime_minutes)->toBe(240)
        ->and((bool) $sheet2->is_incomplete)->toBeFalse();

    // D3's only "punch" is the tail end of D2's overtime — already fully
    // accounted for on D2's sheet. D3 must not also claim it as its own
    // orphan overtime-out, and must not be flagged incomplete for it.
    expect((int) $sheet3->overtime_minutes)->toBe(0)
        ->and((bool) $sheet3->is_incomplete)->toBeFalse();
});

it('respects a configurable max overtime minutes setting', function () {
    app(PayrollSettingService::class)->set('max_overtime_minutes', '60');

    $date = '2026-08-10';

    otPunch($this->employee, $date, '08:00', PunchType::IN);
    otPunch($this->employee, $date, '12:00', PunchType::LUNCH_OUT);
    otPunch($this->employee, $date, '13:00', PunchType::LUNCH_IN);
    otPunch($this->employee, $date, '17:00', PunchType::OUT);
    otPunch($this->employee, $date, '18:00', PunchType::OVERTIME_IN);
    otPunch($this->employee, $date, '20:00', PunchType::OVERTIME_OUT); // 120 min, now over the 60-min cap

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->overtime_minutes)->toBe(120)
        ->and((float) $sheet->overtime_pay)->toBe(0.0)
        ->and((bool) $sheet->is_incomplete)->toBeTrue()
        ->and($sheet->incomplete_reason)->toContain('60');
});
