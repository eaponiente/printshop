<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\Salary;
use App\Models\Payroll\SssContributionBracket;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;
use Payroll\Attendance\Services\PayrollPeriodService;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => 'Test Branch']);

    $this->admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branch->id,
    ]);

    $this->employee = Employee::create([
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'branch_id' => $this->branch->id,
        'current_daily_rate' => 510,
        'status' => 'active',
        'hire_date' => now()->subYear()->toDateString(),
        'position' => 'regular',
    ]);

    Salary::createForEmployee($this->employee, 510, now()->subYear()->toDateString(), 'Initial');

    EmployeeSchedule::create([
        'employee_id' => $this->employee->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0, 6],
        'effective_from' => '2026-05-01',
        'is_active' => true,
    ]);

    SssContributionBracket::create([
        'salary_min' => 0,
        'salary_max' => 20000,
        'employee_percentage' => 5,
        'employer_percentage' => 10,
        'effective_from' => '2026-01-01',
    ]);

    $this->service = app(AttendanceService::class);
});

function punchFullDay(Employee $employee, string $date): void
{
    TimeLog::create([
        'employee_id' => $employee->id,
        'timestamp' => Carbon::parse("{$date} 08:00:00"),
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $employee->id,
        'timestamp' => Carbon::parse("{$date} 12:00:00"),
        'type' => PunchType::LUNCH_OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $employee->id,
        'timestamp' => Carbon::parse("{$date} 13:00:00"),
        'type' => PunchType::LUNCH_IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $employee->id,
        'timestamp' => Carbon::parse("{$date} 17:00:00"),
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);
}

it('increases daily_wage by exactly the incentive amount', function () {
    $date = '2026-05-25';
    punchFullDay($this->employee, $date);

    $sheetBefore = $this->service->processDailyAttendance($this->employee, $date);
    $wageBefore = (float) $sheetBefore->daily_wage;

    expect($wageBefore)->toBe(510.0);

    $this->actingAs($this->admin)
        ->patch(route('payroll.attendance.incentive.update', $this->employee), [
            'date' => $date,
            'incentive' => 100,
        ])
        ->assertRedirect();

    $sheetAfter = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $date)
        ->first();

    expect((float) $sheetAfter->incentive)->toBe(100.0);
    expect((float) $sheetAfter->daily_wage)->toBe($wageBefore + 100.0);
});

it('survives reprocessing of the attendance sheet', function () {
    $date = '2026-05-25';
    punchFullDay($this->employee, $date);

    $this->service->processDailyAttendance($this->employee, $date);

    $this->actingAs($this->admin)
        ->patch(route('payroll.attendance.incentive.update', $this->employee), [
            'date' => $date,
            'incentive' => 75,
        ])
        ->assertRedirect();

    // Reprocess again (e.g. triggered by a punch correction elsewhere).
    $sheet = $this->service->processDailyAttendance($this->employee, $date);

    expect((float) $sheet->incentive)->toBe(75.0);
    expect((float) $sheet->daily_wage)->toBe(585.0); // 510 + 75
});

it('rolls the incentive up into the payroll period item and gross pay', function () {
    $date = '2026-05-25';
    punchFullDay($this->employee, $date);
    $this->service->processDailyAttendance($this->employee, $date);

    $this->actingAs($this->admin)
        ->patch(route('payroll.attendance.incentive.update', $this->employee), [
            'date' => $date,
            'incentive' => 50,
        ])
        ->assertRedirect();

    $wage = (float) AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $date)
        ->first()
        ->daily_wage;

    $periodService = app(PayrollPeriodService::class);
    $period = $periodService->generate($this->branch, '2026-05-25', '2026-05-25');

    $item = $period->items()->where('employee_id', $this->employee->id)->first();

    expect((float) $item->incentive)->toBe(50.0);
    expect((float) $item->gross_pay)->toBe($wage);
    expect((float) $item->gross_pay)->toBe(560.0); // 510 + 50
});

it('rejects incentive updates on a locked attendance sheet', function () {
    $date = '2026-05-25';
    punchFullDay($this->employee, $date);
    $this->service->processDailyAttendance($this->employee, $date);

    // Lock the sheet by generating a payroll period covering that date.
    $periodService = app(PayrollPeriodService::class);
    $periodService->generate($this->branch, '2026-05-25', '2026-05-25');

    $sheet = AttendanceSheet::where('employee_id', $this->employee->id)
        ->where('date', $date)
        ->first();
    expect($sheet->locked_at)->not->toBeNull();

    $response = $this->actingAs($this->admin)
        ->patch(route('payroll.attendance.incentive.update', $this->employee), [
            'date' => $date,
            'incentive' => 200,
        ]);

    $response->assertSessionHasErrors('error');

    $sheet->refresh();
    expect((float) $sheet->incentive)->toBe(0.0);
});
