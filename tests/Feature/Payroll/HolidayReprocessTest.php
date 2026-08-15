<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\TimeLog;
use Payroll\Attendance\Enums\HolidayType;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;
use Payroll\Attendance\Services\HolidayService;

// NOTE: PayrollPeriodTest.php defines `createEmployeeWithAttendance()` /
// `addCompletePunches()` as file-scoped globals, and BranchPayablesReportTest.php
// deliberately does NOT reuse them (it defines its own
// `createEmployeeWithAttendanceForPayables()` instead) rather than risk a
// "Cannot redeclare function" fatal if both files are loaded in the same PHPUnit
// process. This file follows that established convention: it mirrors the same
// fixture-building approach (employee + schedule + real punches processed
// through AttendanceService) under distinct, file-local names.

beforeEach(function () {
    $this->branchA = Branch::factory()->create(['name' => 'Branch A']);
    $this->branchB = Branch::factory()->create(['name' => 'Branch B']);
});

function punchFullDayAndProcess(Employee $employee, string $date): AttendanceSheet
{
    foreach ([
        [PunchType::IN, '08:00:00'],
        [PunchType::LUNCH_OUT, '12:00:00'],
        [PunchType::LUNCH_IN, '13:00:00'],
        [PunchType::OUT, '17:00:00'],
    ] as [$type, $time]) {
        TimeLog::create([
            'employee_id' => $employee->id,
            'type' => $type,
            'source' => PunchSource::SELF_SERVICE,
            'timestamp' => "{$date} {$time}",
        ]);
    }

    app(AttendanceService::class)->processDailyAttendance($employee, $date);

    return AttendanceSheet::where('employee_id', $employee->id)->where('date', $date)->firstOrFail();
}

function makeEmployeeWithAttendanceSheet(Branch $branch, string $name, string $date, float $dailyRate = 500): Employee
{
    $employee = Employee::create([
        'first_name' => $name,
        'last_name' => 'Test',
        'branch_id' => $branch->id,
        'hire_date' => '2020-01-01',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => $dailyRate,
    ]);

    EmployeeSchedule::create([
        'employee_id' => $employee->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0, 6],
        'effective_from' => '2020-01-01',
        'is_active' => true,
    ]);

    punchFullDayAndProcess($employee, $date);

    return $employee;
}

it('creating a branch-scoped special holiday updates only that branch\'s unlocked sheet', function () {
    $date = '2026-09-15'; // Tuesday
    $empA = makeEmployeeWithAttendanceSheet($this->branchA, 'A', $date);
    $empB = makeEmployeeWithAttendanceSheet($this->branchB, 'B', $date);

    $sheetA = AttendanceSheet::where('employee_id', $empA->id)->where('date', $date)->first();
    $sheetB = AttendanceSheet::where('employee_id', $empB->id)->where('date', $date)->first();
    expect($sheetA->holiday_type)->toBeNull();
    expect($sheetB->holiday_type)->toBeNull();

    app(HolidayService::class)->create([
        'name' => 'Branch A Special',
        'date' => $date,
        'type' => HolidayType::SPECIAL,
        'recurring' => false,
    ], [$this->branchA->id]);

    $sheetA->refresh();
    $sheetB->refresh();

    expect($sheetA->holiday_type)->toBe('special');
    expect((float) $sheetA->holiday_pay)->toBeGreaterThan(0.0);
    expect($sheetB->holiday_type)->toBeNull();
    expect((float) $sheetB->holiday_pay)->toBe(0.0);
});

it('does not touch a locked sheet on the same date', function () {
    $date = '2026-09-15';
    $empA = makeEmployeeWithAttendanceSheet($this->branchA, 'A', $date);
    $sheet = AttendanceSheet::where('employee_id', $empA->id)->where('date', $date)->first();
    $sheet->update(['locked_at' => now()]);
    $sheet->refresh();

    $beforeType = $sheet->holiday_type;
    $beforePay = (float) $sheet->holiday_pay;
    $beforeWage = (float) $sheet->daily_wage;
    $beforeLockedAt = $sheet->locked_at->toDateTimeString();

    app(HolidayService::class)->create([
        'name' => 'Branch A Special',
        'date' => $date,
        'type' => HolidayType::SPECIAL,
        'recurring' => false,
    ], [$this->branchA->id]);

    $sheet->refresh();

    expect($sheet->holiday_type)->toBe($beforeType);
    expect((float) $sheet->holiday_pay)->toBe($beforePay);
    expect((float) $sheet->daily_wage)->toBe($beforeWage);
    expect($sheet->locked_at->toDateTimeString())->toBe($beforeLockedAt);
});

it('deleting the holiday clears holiday fields and restores daily_wage', function () {
    $date = '2026-09-15';
    $empA = makeEmployeeWithAttendanceSheet($this->branchA, 'A', $date, 500);
    $sheet = AttendanceSheet::where('employee_id', $empA->id)->where('date', $date)->first();
    $plainWage = (float) $sheet->daily_wage;

    $holiday = app(HolidayService::class)->create([
        'name' => 'Branch A Special',
        'date' => $date,
        'type' => HolidayType::SPECIAL,
        'recurring' => false,
    ], [$this->branchA->id]);

    $sheet->refresh();
    expect($sheet->holiday_type)->toBe('special');
    expect((float) $sheet->holiday_pay)->toBeGreaterThan(0.0);

    app(HolidayService::class)->delete($holiday);

    $sheet->refresh();
    expect($sheet->holiday_type)->toBeNull();
    expect($sheet->holiday_pay_percent)->toBeNull();
    expect((float) $sheet->holiday_pay)->toBe(0.0);
    expect((float) $sheet->daily_wage)->toBe($plainWage);
});

it('narrowing a nationwide holiday to branch A clears branch B\'s unlocked sheet', function () {
    $date = '2026-09-15';
    $empA = makeEmployeeWithAttendanceSheet($this->branchA, 'A', $date);
    $empB = makeEmployeeWithAttendanceSheet($this->branchB, 'B', $date);

    $holiday = app(HolidayService::class)->create([
        'name' => 'Nationwide Special',
        'date' => $date,
        'type' => HolidayType::SPECIAL,
        'recurring' => false,
    ], []); // nationwide

    $sheetA = AttendanceSheet::where('employee_id', $empA->id)->where('date', $date)->first();
    $sheetB = AttendanceSheet::where('employee_id', $empB->id)->where('date', $date)->first();
    expect($sheetA->holiday_type)->toBe('special');
    expect($sheetB->holiday_type)->toBe('special');

    app(HolidayService::class)->update($holiday, [
        'name' => 'Nationwide Special',
        'date' => $date,
        'type' => HolidayType::SPECIAL,
        'recurring' => false,
    ], [$this->branchA->id]);

    $sheetA->refresh();
    $sheetB->refresh();

    expect($sheetA->holiday_type)->toBe('special');
    expect((float) $sheetA->holiday_pay)->toBeGreaterThan(0.0);
    expect($sheetB->holiday_type)->toBeNull();
    expect((float) $sheetB->holiday_pay)->toBe(0.0);
});

it('moving the holiday date clears the old date\'s sheet and sets the new date\'s', function () {
    $d1 = '2026-09-15'; // Tuesday
    $d2 = '2026-09-16'; // Wednesday
    $emp = makeEmployeeWithAttendanceSheet($this->branchA, 'A', $d1);
    punchFullDayAndProcess($emp, $d2);

    $holiday = app(HolidayService::class)->create([
        'name' => 'Movable Special',
        'date' => $d1,
        'type' => HolidayType::SPECIAL,
        'recurring' => false,
    ], [$this->branchA->id]);

    $sheet1 = AttendanceSheet::where('employee_id', $emp->id)->where('date', $d1)->first();
    $sheet2 = AttendanceSheet::where('employee_id', $emp->id)->where('date', $d2)->first();
    expect($sheet1->holiday_type)->toBe('special');
    expect($sheet2->holiday_type)->toBeNull();

    app(HolidayService::class)->update($holiday, [
        'name' => 'Movable Special',
        'date' => $d2,
        'type' => HolidayType::SPECIAL,
        'recurring' => false,
    ], [$this->branchA->id]);

    $sheet1->refresh();
    $sheet2->refresh();

    expect($sheet1->holiday_type)->toBeNull();
    expect((float) $sheet1->holiday_pay)->toBe(0.0);
    expect($sheet2->holiday_type)->toBe('special');
    expect((float) $sheet2->holiday_pay)->toBeGreaterThan(0.0);
});

it('a recurring holiday updates unlocked sheets across years but skips a locked one', function () {
    // All three sit within the 1-year reprocessing fan-out guard window
    // (`now()->subYear()`), unlike a hardcoded past year could risk.
    $y1 = '2026-07-01';
    $y2 = '2027-07-01';
    $y3 = '2028-07-01'; // will be locked

    $emp = makeEmployeeWithAttendanceSheet($this->branchA, 'A', $y1);
    punchFullDayAndProcess($emp, $y2);
    punchFullDayAndProcess($emp, $y3);

    AttendanceSheet::where('employee_id', $emp->id)->where('date', $y3)->update(['locked_at' => now()]);

    app(HolidayService::class)->create([
        'name' => 'Recurring Special',
        'date' => $y1,
        'type' => HolidayType::SPECIAL,
        'recurring' => true,
    ], [$this->branchA->id]);

    $s1 = AttendanceSheet::where('employee_id', $emp->id)->where('date', $y1)->first();
    $s2 = AttendanceSheet::where('employee_id', $emp->id)->where('date', $y2)->first();
    $s3 = AttendanceSheet::where('employee_id', $emp->id)->where('date', $y3)->first();

    expect($s1->holiday_type)->toBe('special');
    expect($s2->holiday_type)->toBe('special');
    expect($s3->holiday_type)->toBeNull(); // locked, untouched
});

it('never creates a new attendance sheet while reprocessing', function () {
    $date = '2026-09-15';
    makeEmployeeWithAttendanceSheet($this->branchA, 'A', $date);
    makeEmployeeWithAttendanceSheet($this->branchB, 'B', $date);

    $baseline = AttendanceSheet::count();

    $holiday = app(HolidayService::class)->create([
        'name' => 'Count Guard',
        'date' => $date,
        'type' => HolidayType::SPECIAL,
        'recurring' => false,
    ], [$this->branchA->id]);
    expect(AttendanceSheet::count())->toBe($baseline);

    app(HolidayService::class)->update($holiday, [
        'name' => 'Count Guard',
        'date' => $date,
        'type' => HolidayType::SPECIAL,
        'recurring' => false,
    ], [$this->branchA->id, $this->branchB->id]);
    expect(AttendanceSheet::count())->toBe($baseline);

    app(HolidayService::class)->delete($holiday);
    expect(AttendanceSheet::count())->toBe($baseline);
});
