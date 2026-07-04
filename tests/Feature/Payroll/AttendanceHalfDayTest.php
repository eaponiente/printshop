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
        'first_name' => 'Half',
        'last_name' => 'Day',
        'branch_id' => $branch->id,
        'current_daily_rate' => 510,
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
    $this->hourlyRate = 510 / 8;
});

// --- Morning half-day (8am–12pm) ---

it('gives half-day pay for morning half (in 8am, out 12pm)', function () {
    $date = '2026-06-01';

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 08:00"),
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 12:00"),
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(0);
    expect((int) $sheet->undertime_minutes)->toBe(240);       // 4h short of 8h
    expect((float) $sheet->fine_deduction)->toBe(0.0);        // no no-break fine for half-day
    expect((float) $sheet->daily_wage)->toBe(round(510 - 4 * $this->hourlyRate, 2)); // 255.00
});

it('charges more undertime when morning half-day employee leaves before noon', function () {
    $date = '2026-06-01';

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 08:00"),
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 10:00"), // left 2h early
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    // Worked 2h; full day 8h → 6h undertime
    expect((int) $sheet->undertime_minutes)->toBe(360);
    expect((float) $sheet->daily_wage)->toBe(round(510 - 6 * $this->hourlyRate, 2));
});

it('applies late deduction without double-charging undertime on morning half', function () {
    $date = '2026-06-01';

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 08:15"),
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 12:00"),
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    // Late: 15 min × ₱10 = ₱150. Undertime anchored from 8am (not 8:15am),
    // so undertime = 480 − 240 = 240 min = 4h. Late does not inflate undertime.
    expect((int) $sheet->late_minutes)->toBe(15);
    expect((int) $sheet->undertime_minutes)->toBe(240);
    expect((float) $sheet->daily_wage)->toBe(round(510 - 150 - 4 * $this->hourlyRate, 2)); // 105.00
});

// --- Afternoon half-day (1pm–5pm) ---

it('gives half-day pay for afternoon half (in 1pm, out 5pm)', function () {
    $date = '2026-06-01';

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 13:00"),
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 17:00"),
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(0);              // no late penalty for afternoon half
    expect((int) $sheet->undertime_minutes)->toBe(240);       // 4h short of 8h
    expect((float) $sheet->fine_deduction)->toBe(0.0);        // no no-break fine
    expect((float) $sheet->daily_wage)->toBe(round(510 - 4 * $this->hourlyRate, 2)); // 255.00
});

it('charges undertime when afternoon half-day employee leaves early', function () {
    $date = '2026-06-01';

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 13:00"),
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 16:00"), // left 1h before paid end
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    // Worked 3h (1pm–4pm); full day 8h → 5h undertime, no late
    expect((int) $sheet->late_minutes)->toBe(0);
    expect((int) $sheet->undertime_minutes)->toBe(300);
    expect((string) $sheet->daily_wage)->toBe((string) round(510 - 5 * $this->hourlyRate, 2));
});

it('afternoon half respects 5:30pm schedule tail (5pm–5:30pm is unpaid)', function () {
    // Adjust schedule: 8am–5:30pm, with 30-min unpaid tail
    EmployeeSchedule::where('employee_id', $this->employee->id)->update([
        'end_time' => '17:30',
        'unpaid_tail_minutes' => 30,
    ]);

    $date = '2026-06-01';

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 13:00"),
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 17:30"), // leaves at end of schedule
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    // Paid end = 5pm (5:30pm − 30 min tail).
    // Worked (paid): 1pm–5pm = 4h. Full day paid = 480 min → 4h undertime.
    expect((int) $sheet->late_minutes)->toBe(0);
    expect((int) $sheet->undertime_minutes)->toBe(240);
    expect((float) $sheet->fine_deduction)->toBe(0.0);
    expect((float) $sheet->daily_wage)->toBe(round(510 - 4 * $this->hourlyRate, 2)); // 255.00
});

// --- Negative: full-day employees are not affected ---

it('does not treat leaving after 1pm as a morning half-day', function () {
    $date = '2026-06-01';

    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 08:00"),
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 14:00"), // out at 2pm — not morning half
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    // Not a morning half (OUT > 1pm). No-break fine applies (worked 6h, no lunch punches).
    // Undertime: paidEndTime(5pm) − outTime(2pm) = 3h
    expect((int) $sheet->late_minutes)->toBe(0);
    expect((int) $sheet->undertime_minutes)->toBe(180);
    expect((float) $sheet->fine_deduction)->toBe(20.0);
});

// --- Late ≥ 1 hour in the morning becomes an afternoon-only half day ---

function hdPunch(Employee $employee, string $date, string $time, PunchType $type): void
{
    TimeLog::create([
        'employee_id' => $employee->id,
        'timestamp' => Carbon::parse("{$date} {$time}"),
        'type' => $type,
        'source' => PunchSource::SELF_SERVICE,
    ]);
}

it('turns exactly 1 hour late into a half day with no late deduction', function () {
    $date = '2026-06-01';
    hdPunch($this->employee, $date, '09:00', PunchType::IN); // 60 min late
    hdPunch($this->employee, $date, '17:00', PunchType::OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(0);
    expect((float) $sheet->late_deduction)->toBe(0.0);
    expect((int) $sheet->undertime_minutes)->toBe(240);
    expect((float) $sheet->daily_wage)->toBe(round(510 - 4 * $this->hourlyRate, 2)); // 255
});

it('pays a flat afternoon regardless of the exact (late) morning arrival', function () {
    $date = '2026-06-01';
    hdPunch($this->employee, $date, '10:30', PunchType::IN); // deep into the morning
    hdPunch($this->employee, $date, '17:00', PunchType::OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((float) $sheet->late_deduction)->toBe(0.0);
    expect((float) $sheet->daily_wage)->toBe(round(510 - 4 * $this->hourlyRate, 2)); // 255
});

it('pays 1pm–5pm when arriving at noon', function () {
    $date = '2026-06-01';
    hdPunch($this->employee, $date, '12:00', PunchType::IN);
    hdPunch($this->employee, $date, '17:00', PunchType::OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(0);
    expect((float) $sheet->daily_wage)->toBe(round(510 - 4 * $this->hourlyRate, 2)); // 255
});

it('keeps the full half-day wage and deducts only the late penalty when late for the afternoon', function () {
    $date = '2026-06-01';
    hdPunch($this->employee, $date, '13:10', PunchType::IN); // 10 min late for 1pm
    hdPunch($this->employee, $date, '17:00', PunchType::OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    $lateDeduction = round(10 * 10, 2); // 10 min flat, within threshold

    expect((int) $sheet->late_minutes)->toBe(10);
    expect((float) $sheet->late_deduction)->toBe($lateDeduction);
    expect((int) $sheet->undertime_minutes)->toBe(240); // full afternoon still paid
    expect((float) $sheet->daily_wage)->toBe(round(510 - 4 * $this->hourlyRate - $lateDeduction, 2)); // 255 − 100 = 155
});

it('caps the afternoon-late deduction at the threshold (no hourly escalation)', function () {
    $date = '2026-06-01';
    hdPunch($this->employee, $date, '13:45', PunchType::IN); // 45 min late for 1pm
    hdPunch($this->employee, $date, '17:00', PunchType::OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    // Capped at the 20-min threshold × ₱10 = ₱200 (never the hourly-rate formula).
    expect((int) $sheet->late_minutes)->toBe(45);
    expect((float) $sheet->late_deduction)->toBe(200.0);
    expect((int) $sheet->undertime_minutes)->toBe(240); // full afternoon still paid
    expect((float) $sheet->daily_wage)->toBe(round(510 - 4 * $this->hourlyRate - 200, 2)); // 55
});

it('keeps 59 minutes late as a full day (just under the 1-hour cutoff)', function () {
    $date = '2026-06-01';
    hdPunch($this->employee, $date, '08:59', PunchType::IN);
    hdPunch($this->employee, $date, '12:00', PunchType::LUNCH_OUT);
    hdPunch($this->employee, $date, '13:00', PunchType::LUNCH_IN);
    hdPunch($this->employee, $date, '17:00', PunchType::OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(59);
    expect((float) $sheet->late_deduction)->toBeGreaterThan(0.0);
    expect((float) $sheet->daily_wage)->toBeGreaterThan(255.0); // full day, not the flat half
});

it('respects a configurable half-day threshold', function () {
    app(PayrollSettingService::class)->set('half_day_threshold_minutes', '90');

    $date = '2026-06-01';
    hdPunch($this->employee, $date, '09:05', PunchType::IN); // 65 min late, under the 90 cutoff
    hdPunch($this->employee, $date, '12:00', PunchType::LUNCH_OUT);
    hdPunch($this->employee, $date, '13:00', PunchType::LUNCH_IN);
    hdPunch($this->employee, $date, '17:00', PunchType::OUT);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(65);
    expect((float) $sheet->late_deduction)->toBeGreaterThan(0.0);
    expect((float) $sheet->daily_wage)->not->toBe(255.0);
});
