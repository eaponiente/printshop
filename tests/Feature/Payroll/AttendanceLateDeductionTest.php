<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\TimeLog;
use Carbon\Carbon;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;

beforeEach(function () {
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

it('applies 5php per minute for first 20 minutes late', function () {
    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:15');

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(15);
    expect($sheet->late_deduction)->toBe('75.00');
});

it('charges 100php at exactly 20 minutes late', function () {
    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:20');

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((int) $sheet->late_minutes)->toBe(20);
    expect($sheet->late_deduction)->toBe('100.00');
});

it('charges 5php/min for first 20min plus hourly_rate/60 after 20min', function () {
    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:25');

    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    $hourlyRate = 510 / 8;

    $expected = round((20 * 5) + (5 * ($hourlyRate / 60)), 2);

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
    $expected = round((20 * 5) + (45 * ($hourlyRate / 60)), 2);

    expect((int) $sheet->late_minutes)->toBe(65);
    expect((string) $sheet->late_deduction)->toBe((string) $expected);
});

it('recalculates sheets via artisan command', function () {
    $date = '2026-06-01';
    punchInAt($this->employee, $date, '08:25');

    $this->service->processDailyAttendance($this->employee, $date);

    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $date)
        ->first();

    $hourlyRate = 510 / 8;
    $expected = round((20 * 5) + (5 * ($hourlyRate / 60)), 2);
    expect((string) $sheet->late_deduction)->toBe((string) $expected);
});
