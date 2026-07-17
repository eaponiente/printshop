<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\Employee;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\Salary;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Payroll\Attendance\Services\PayrollPeriodService;
use Payroll\Attendance\Services\WorkWeekTableService;

beforeEach(function () {
    $this->branchA = Branch::factory()->create(['name' => 'Branch A']);
    $this->branchB = Branch::factory()->create(['name' => 'Branch B']);

    $this->superadmin = User::factory()->create([
        'role' => 'superadmin',
        'branch_id' => null,
    ]);

    $this->adminA = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branchA->id,
    ]);

    $this->staffA = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branchA->id,
    ]);
});

function createWorkWeekEmployee(
    Branch $branch,
    string $name,
    float $dailyRate = 500,
    array $govtIds = ['sss' => '123', 'phic' => '456', 'pagibig' => '789']
): Employee {
    $emp = Employee::create([
        'first_name' => $name,
        'last_name' => 'Test',
        'branch_id' => $branch->id,
        'hire_date' => '2026-01-05',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => $dailyRate,
        'sss_number' => $govtIds['sss'] ?? null,
        'philhealth_number' => $govtIds['phic'] ?? null,
        'pagibig_number' => $govtIds['pagibig'] ?? null,
        'sss_deduction_per_week' => 165.75,
        'philhealth_deduction_per_week' => 82.88,
        'pagibig_deduction_per_week' => 50.00,
    ]);

    if (! Salary::where('employee_id', $emp->id)->exists()) {
        Salary::createForEmployee($emp, $dailyRate, '2026-01-05', 'Initial');
    }

    return $emp;
}

function createWorkWeekSheet(Employee $employee, string $date, array $overrides = []): AttendanceSheet
{
    return AttendanceSheet::create(array_merge([
        'employee_id' => $employee->id,
        'date' => $date,
        'schedule_start_time' => '08:00',
        'schedule_end_time' => '17:00',
        'rest_days' => [0],
        'daily_rate' => $employee->current_daily_rate,
        'hours_worked' => 8,
        'daily_wage' => $employee->current_daily_rate,
        'is_present' => true,
        'is_rest_day' => false,
        'late_minutes' => 0,
        'late_deduction' => 0,
        'undertime_minutes' => 0,
        'undertime_deduction' => 0,
        'overtime_minutes' => 0,
        'overtime_pay' => 0,
        'fine_deduction' => 0,
        'holiday_pay' => 0,
    ], $overrides));
}

// Sat 2026-05-23 through Fri 2026-05-29 (the same week PayrollPeriodTest uses
// for its Mon-Fri 2026-05-25..29 range, extended back to the prior Saturday).
const WW_START = '2026-05-23';
const WW_END = '2026-05-29';

it('locks admin to their own branch regardless of branch_id input', function () {
    createWorkWeekEmployee($this->branchA, 'InBranch');

    $this->actingAs($this->adminA)
        ->get(route('payroll.work-week.index', [
            'branch_id' => $this->branchB->id,
            'start_date' => WW_START,
            'end_date' => WW_END,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page->where('branch.id', $this->branchA->id)
        );
});

it('defaults superadmin to the alphabetically-first branch when none is selected', function () {
    Branch::factory()->create(['name' => 'Zeta Branch']);
    $alpha = Branch::factory()->create(['name' => 'Alpha Branch']);
    Branch::factory()->create(['name' => 'Mid Branch']);

    $this->actingAs($this->superadmin)
        ->get(route('payroll.work-week.index', [
            'start_date' => WW_START,
            'end_date' => WW_END,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page->where('branch.id', $alpha->id)
        );
});

it('honors an explicit branch_id for superadmin', function () {
    $this->actingAs($this->superadmin)
        ->get(route('payroll.work-week.index', [
            'branch_id' => $this->branchB->id,
            'start_date' => WW_START,
            'end_date' => WW_END,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page->where('branch.id', $this->branchB->id)
        );
});

it('forbids staff from viewing the work week table', function () {
    $this->actingAs($this->staffA)
        ->get(route('payroll.work-week.index', [
            'start_date' => WW_START,
            'end_date' => WW_END,
        ]))
        ->assertForbidden();
});

it('surfaces raw day-cell fields for each attendance status', function () {
    $present = createWorkWeekEmployee($this->branchA, 'Present');
    createWorkWeekSheet($present, '2026-05-25');

    $late = createWorkWeekEmployee($this->branchA, 'Late');
    createWorkWeekSheet($late, '2026-05-25', ['late_minutes' => 15, 'late_deduction' => 75]);

    $absent = createWorkWeekEmployee($this->branchA, 'Absent');
    createWorkWeekSheet($absent, '2026-05-25', ['is_present' => false, 'absence_type' => 'unexcused', 'daily_wage' => 0]);

    $holiday = createWorkWeekEmployee($this->branchA, 'Holiday');
    createWorkWeekSheet($holiday, '2026-05-25', ['holiday_type' => 'regular', 'holiday_pay_percent' => 200, 'holiday_pay' => 500]);

    $onLeave = createWorkWeekEmployee($this->branchA, 'OnLeave');
    createWorkWeekSheet($onLeave, '2026-05-25', ['leave_type' => 'sick', 'is_present' => false]);

    $onRest = createWorkWeekEmployee($this->branchA, 'OnRest');
    createWorkWeekSheet($onRest, '2026-05-25', ['is_rest_day' => true, 'is_present' => false, 'daily_wage' => 0]);

    $service = app(WorkWeekTableService::class);
    $full = $service->buildFull($this->branchA, WW_START, WW_END);

    expect($full['cells']["{$present->id}-2026-05-25"]['is_present'])->toBeTrue();
    expect($full['cells']["{$late->id}-2026-05-25"]['late_minutes'])->toBe(15);
    expect($full['cells']["{$absent->id}-2026-05-25"]['is_present'])->toBeFalse();
    expect($full['cells']["{$holiday->id}-2026-05-25"]['holiday_pay_percent'])->toBe(200.0);
    expect($full['cells']["{$onLeave->id}-2026-05-25"]['leave_type'])->toBe('sick');
    expect($full['cells']["{$onRest->id}-2026-05-25"]['is_rest_day'])->toBeTrue();
});

it('shows Sunday as its own column and still folds it into totals', function () {
    $emp = createWorkWeekEmployee($this->branchA, 'SundayWorker');
    createWorkWeekSheet($emp, '2026-05-24', [ // Sunday
        'is_rest_day' => true,
        'overtime_minutes' => 120,
        'overtime_pay' => 250,
        'daily_wage' => 250,
    ]);

    // Sunday is now a dedicated grid column (Sat -> Fri, 7 columns in order).
    $this->actingAs($this->adminA)
        ->get(route('payroll.work-week.index', [
            'start_date' => WW_START,
            'end_date' => WW_END,
        ]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('dayColumns', ['2026-05-23', '2026-05-24', '2026-05-25', '2026-05-26', '2026-05-27', '2026-05-28', '2026-05-29'])
                ->etc()
        );

    // Sunday data still feeds the row/footer totals and has its own cell.
    $service = app(WorkWeekTableService::class);
    $full = $service->buildFull($this->branchA, WW_START, WW_END);

    expect($full['rowTotals'][$emp->id]['overtime_minutes'])->toBe(120);
    expect($full['footerTotals']['total_overtime_hours'])->toBe(2.0);
    expect(array_key_exists("{$emp->id}-2026-05-24", $full['cells']))->toBeTrue();
});

it('previews cash advance FIFO deduction without mutating balances', function () {
    $emp = createWorkWeekEmployee($this->branchA, 'Borrower');
    for ($i = 0; $i < 5; $i++) {
        createWorkWeekSheet($emp, ['2026-05-23', '2026-05-25', '2026-05-26', '2026-05-27', '2026-05-28', '2026-05-29'][$i] ?? '2026-05-29');
    }

    $older = CashAdvance::create([
        'employee_id' => $emp->id,
        'amount' => 200,
        'remaining_balance' => 200,
        'status' => 'approved',
        'reason' => 'Older advance',
        'created_at' => now()->subDays(2),
    ]);
    $newer = CashAdvance::create([
        'employee_id' => $emp->id,
        'amount' => 1000,
        'remaining_balance' => 1000,
        'status' => 'approved',
        'reason' => 'Newer advance',
        'created_at' => now()->subDay(),
    ]);

    $service = app(WorkWeekTableService::class);
    $full = $service->buildFull($this->branchA, WW_START, WW_END);

    $totals = $full['rowTotals'][$emp->id];
    expect($totals['cash_advance'])->toBeGreaterThan(0);

    $older->refresh();
    $newer->refresh();
    expect((float) $older->remaining_balance)->toBe(200.0);
    expect((float) $newer->remaining_balance)->toBe(1000.0);
    expect($older->status)->toBe('approved');
    expect($newer->status)->toBe('approved');
});

it('matches PayrollPeriodService gross and net pay for the same inputs, absent de minimis', function () {
    $emp = createWorkWeekEmployee($this->branchA, 'Parity', 510);
    foreach (['2026-05-25', '2026-05-26', '2026-05-27', '2026-05-28', '2026-05-29'] as $date) {
        createWorkWeekSheet($emp, $date);
    }

    $service = app(WorkWeekTableService::class);
    $full = $service->buildFull($this->branchA, '2026-05-25', '2026-05-29');
    $wwTotals = $full['rowTotals'][$emp->id];

    $period = app(PayrollPeriodService::class)
        ->generate($this->branchA, '2026-05-25', '2026-05-29');
    $item = $period->items()->where('employee_id', $emp->id)->first();

    expect((float) $wwTotals['gross_pay'])->toBe((float) $item->gross_pay);
    expect((float) $wwTotals['net_pay'])->toBe((float) $item->net_pay);
});

it('runs a bounded, roughly-constant number of queries regardless of employee count', function () {
    for ($n = 0; $n < 5; $n++) {
        createWorkWeekEmployee($this->branchA, "Small{$n}");
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($this->adminA)
        ->get(route('payroll.work-week.index', ['start_date' => WW_START, 'end_date' => WW_END]));
    $smallCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $branchC = Branch::factory()->create(['name' => 'Branch C']);
    $adminC = User::factory()->create(['role' => 'admin', 'branch_id' => $branchC->id]);
    for ($n = 0; $n < 20; $n++) {
        createWorkWeekEmployee($branchC, "Big{$n}");
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($adminC)
        ->get(route('payroll.work-week.index', ['start_date' => WW_START, 'end_date' => WW_END]));
    $bigCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($bigCount)->toBeLessThan($smallCount + 5);
});

it('never creates a PayrollPeriod or locks attendance sheets as a side effect', function () {
    $emp = createWorkWeekEmployee($this->branchA, 'ReadOnly');
    createWorkWeekSheet($emp, '2026-05-25');

    $this->actingAs($this->adminA)
        ->get(route('payroll.work-week.index', ['start_date' => WW_START, 'end_date' => WW_END]))
        ->assertOk();

    $this->actingAs($this->adminA)
        ->get(route('payroll.work-week.print', ['start_date' => WW_START, 'end_date' => WW_END]))
        ->assertOk();

    expect(PayrollPeriod::count())->toBe(0);
    expect(AttendanceSheet::whereNotNull('locked_at')->count())->toBe(0);
});
