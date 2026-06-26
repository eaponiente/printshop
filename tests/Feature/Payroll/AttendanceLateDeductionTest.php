<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
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

    $this->branch = Branch::factory()->create(['name' => 'Test Branch']);

    $this->employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'branch_id' => $this->branch->id,
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
});

function punchInAt(Employee $employee, string $date, string $time): void
{
    TimeLog::create([
        'employee_id' => $employee->id,
        'timestamp' => Carbon::parse("{$date} {$time}"),
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
}

it('applies 10php per minute for first 20 minutes late', function () {
    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:15');

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(15);
    expect($sheet->late_deduction)->toBe('150.00'); // 15 × ₱10
});

it('charges 200php at exactly 20 minutes late', function () {
    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:20');

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(20);
    expect($sheet->late_deduction)->toBe('200.00'); // 20 × ₱10
});

it('charges 10php/min for first 20min plus hourly_rate/60 after 20min', function () {
    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:25');

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    $hourlyRate = 510 / 8;
    $expected = round((20 * 10) + (5 * ($hourlyRate / 60)), 2);

    expect((int) $sheet->late_minutes)->toBe(25);
    expect((string) $sheet->late_deduction)->toBe((string) $expected);
});

it('charges 0 for on-time arrival', function () {
    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:00');

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(0);
    expect($sheet->late_deduction)->toBe('0.00');
});

it('charges correctly for 65 minutes late', function () {
    $date = '2026-06-01';
    punchInAt($this->employee, $date, '09:05');

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    $hourlyRate = 510 / 8;
    $expected = round((20 * 10) + (45 * ($hourlyRate / 60)), 2);

    expect((int) $sheet->late_minutes)->toBe(65);
    expect((string) $sheet->late_deduction)->toBe((string) $expected);
});

it('subtracts late_deduction from daily_wage', function () {
    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:23');
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 12:00"),
        'type' => PunchType::LUNCH_OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 13:00"),
        'type' => PunchType::LUNCH_IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp' => Carbon::parse("{$date} 17:00"),
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    $hourlyRate = 510 / 8;
    $expectedLate = round((20 * 10) + (3 * ($hourlyRate / 60)), 2);

    expect((int) $sheet->late_minutes)->toBe(23);
    expect((string) $sheet->late_deduction)->toBe((string) $expectedLate);

    $expectedWage = round(510 - $expectedLate, 2);
    expect((string) $sheet->daily_wage)->toBe((string) $expectedWage);
});

it('recalculates sheets via artisan command', function () {
    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:25');

    $this->service->processDailyAttendance($this->employee, $date);

    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $date)
        ->first();

    $hourlyRate = 510 / 8;
    $expected = round((20 * 10) + (5 * ($hourlyRate / 60)), 2);
    expect((string) $sheet->late_deduction)->toBe((string) $expected);
});

it('uses custom per-minute deduction from settings', function () {
    app(PayrollSettingService::class)->set('late_deduction_per_minute', '7');

    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:15');

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(15);
    expect((string) $sheet->late_deduction)->toBe('105.00'); // 15 × ₱7
});

it('uses custom threshold from settings', function () {
    app(PayrollSettingService::class)->set('late_deduction_threshold_minutes', '10');

    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:25');

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    $hourlyRate = 510 / 8;
    $expected = round((10 * 10) + (15 * ($hourlyRate / 60)), 2);

    expect((int) $sheet->late_minutes)->toBe(25);
    expect((string) $sheet->late_deduction)->toBe((string) $expected);
});

it('adds no-break fine when employee has punch-in/out but no lunch-break punches', function () {
    $date = '2026-06-01';

    punchInAt($this->employee, $date, '08:00');
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp'   => Carbon::parse("{$date} 17:00"),
        'type'        => PunchType::OUT,
        'source'      => PunchSource::SELF_SERVICE,
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    $noBreakFine = (float) app(PayrollSettingService::class)->get('no_break_fine', 20);

    expect($sheet->is_present)->toBeTrue();
    expect((float) $sheet->fine_deduction)->toBe($noBreakFine);
    expect((float) $sheet->daily_wage)->toBe(round(510 - $noBreakFine, 2));
});

it('does not add no-break fine when employee has lunch-break punches', function () {
    $date = '2026-06-01';

    punchInAt($this->employee, $date, '08:00');
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp'   => Carbon::parse("{$date} 12:00"),
        'type'        => PunchType::LUNCH_OUT,
        'source'      => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp'   => Carbon::parse("{$date} 13:00"),
        'type'        => PunchType::LUNCH_IN,
        'source'      => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp'   => Carbon::parse("{$date} 17:00"),
        'type'        => PunchType::OUT,
        'source'      => PunchSource::SELF_SERVICE,
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect($sheet->is_present)->toBeTrue();
    expect((float) $sheet->fine_deduction)->toBe(0.0);
    expect((float) $sheet->daily_wage)->toBe(510.0);
});

it('no-break fine uses configurable amount from settings', function () {
    app(PayrollSettingService::class)->set('no_break_fine', '50');

    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:00');
    TimeLog::create([
        'employee_id' => $this->employee->id,
        'timestamp'   => Carbon::parse("{$date} 17:00"),
        'type'        => PunchType::OUT,
        'source'      => PunchSource::SELF_SERVICE,
    ]);

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((float) $sheet->fine_deduction)->toBe(50.0);
    expect((float) $sheet->daily_wage)->toBe(460.0);
});
