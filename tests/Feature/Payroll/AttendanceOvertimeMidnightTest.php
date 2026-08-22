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
