<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Benefit;
use App\Models\Payroll\CompanyConfig;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\PayrollPeriodItem;
use App\Models\Payroll\Salary;
use App\Models\Payroll\SssContributionBracket;
use App\Models\User;
use Payroll\Attendance\Services\PayrollPeriodService;

beforeEach(function () {
    $this->branchA = Branch::factory()->create(['name' => 'Branch A']);
    $this->branchB = Branch::factory()->create(['name' => 'Branch B']);

    $this->superadmin = User::factory()->create([
        'role' => 'superadmin',
        'branch_id' => null,
    ]);

    $this->admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branchA->id,
    ]);

    $this->staff = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branchA->id,
    ]);

    SssContributionBracket::create([
        'salary_min' => 0,
        'salary_max' => 20000,
        'employee_percentage' => 5,
        'employer_percentage' => 10,
        'effective_from' => '2026-01-01',
    ]);

    CompanyConfig::updateOrCreate(
        ['key' => 'pagibig_monthly_employer_share'],
        ['value' => '100', 'label' => 'Pag-IBIG Monthly Employer Share'],
    );
});

function createEmployeeWithAttendanceForPayables(
    Branch $branch,
    string $name,
    float $dailyRate = 510,
    array $govtIds = ['sss' => '123', 'phic' => '456', 'pagibig' => '789'],
    int $perfectDays = 5
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
    ]);

    if (! Salary::where('employee_id', $emp->id)->exists()) {
        Salary::createForEmployee($emp, $dailyRate, '2026-01-05', 'Initial');
    }

    EmployeeSchedule::create([
        'employee_id' => $emp->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0, 6],
        'effective_from' => '2026-05-25',
        'is_active' => true,
    ]);

    $dates = ['2026-05-25', '2026-05-26', '2026-05-27', '2026-05-28', '2026-05-29'];
    for ($i = 0; $i < $perfectDays; $i++) {
        AttendanceSheet::create([
            'employee_id' => $emp->id,
            'date' => $dates[$i],
            'schedule_start_time' => '08:00',
            'schedule_end_time' => '17:00',
            'rest_days' => [0, 6],
            'daily_rate' => $dailyRate,
            'hours_worked' => 8,
            'daily_wage' => $dailyRate,
            'is_present' => true,
        ]);
    }

    return $emp;
}

test('employer shares are computed and stored on payroll generation', function () {
    createEmployeeWithAttendanceForPayables($this->branchA, 'John', 510);

    $service = app(PayrollPeriodService::class);
    $period = $service->generate($this->branchA, '2026-05-25', '2026-05-29');

    $item = $period->items()->first();
    expect($item)->not->toBeNull();

    $monthlySalary = 510 * 26;

    // SSS employer = monthlySalary * bracket.employer_percentage / 100 / 4
    $expectedSss = round($monthlySalary * 10 / 100 / 4, 2);
    expect((float) $item->sss_employer)->toBe($expectedSss);

    // PhilHealth employer = same as employee deduction
    $expectedPhilHealth = round($monthlySalary * 0.05 * 0.50 / 4, 2);
    expect((float) $item->philhealth_employer)->toBe($expectedPhilHealth);

    // Pag-IBIG employer = config value / 4 = 100 / 4 = 25
    expect((float) $item->pagibig_employer)->toBe(25.00);
});

test('employer shares are zero when govt numbers are missing', function () {
    createEmployeeWithAttendanceForPayables($this->branchA, 'John', 510, [
        'sss' => null,
        'phic' => null,
        'pagibig' => null,
    ]);

    $service = app(PayrollPeriodService::class);
    $period = $service->generate($this->branchA, '2026-05-25', '2026-05-29');

    $item = $period->items()->first();

    expect((float) $item->sss_employer)->toBe(0.00);
    expect((float) $item->philhealth_employer)->toBe(0.00);
    expect((float) $item->pagibig_employer)->toBe(0.00);
});

test('superadmin can view branch payables report', function () {
    createEmployeeWithAttendanceForPayables($this->branchA, 'John', 510);

    $service = app(PayrollPeriodService::class);
    $period = $service->generate($this->branchA, '2026-05-25', '2026-05-29');
    $service->approve($period, $this->superadmin->id);

    $response = $this->actingAs($this->superadmin)
        ->get(route('payroll.reports.branch-payables', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-06-30',
        ]));

    $response->assertOk();

    $results = $response->viewData('page')['props']['results'];
    expect($results)->toHaveCount(2);

    $branchAResult = collect($results)->firstWhere('branch', 'Branch A');
    expect($branchAResult)->not->toBeNull();
    expect($branchAResult['sss_employer'])->toBeGreaterThan(0);
    expect($branchAResult['philhealth_employer'])->toBeGreaterThan(0);
    expect($branchAResult['pagibig_employer'])->toBeGreaterThan(0);
    expect($branchAResult['total_benefits'])->toBeGreaterThan(0);

    $grandTotal = $response->viewData('page')['props']['grand_total'];
    expect($grandTotal['total_benefits'])->toBeGreaterThan(0);
});

test('admin cannot view branch payables report', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('payroll.reports.branch-payables', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-06-30',
        ]));

    $response->assertForbidden();
});

test('staff cannot view branch payables report', function () {
    $response = $this->actingAs($this->staff)
        ->get(route('payroll.reports.branch-payables', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-06-30',
        ]));

    $response->assertForbidden();
});

test('date range filters payroll periods correctly', function () {
    createEmployeeWithAttendanceForPayables($this->branchA, 'John', 510);

    $service = app(PayrollPeriodService::class);
    $period = $service->generate($this->branchA, '2026-05-25', '2026-05-29');
    $service->approve($period, $this->superadmin->id);

    // Date range outside the period should return zeros
    $response = $this->actingAs($this->superadmin)
        ->get(route('payroll.reports.branch-payables', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]));

    $response->assertOk();

    $results = $response->viewData('page')['props']['results'];
    $totalBenefits = collect($results)->sum('total_benefits');
    expect($totalBenefits)->toBe(0);

    $grandTotal = $response->viewData('page')['props']['grand_total'];
    expect($grandTotal['total_benefits'])->toEqual(0);
});

test('branch filter restricts results to selected branch', function () {
    createEmployeeWithAttendanceForPayables($this->branchA, 'John', 510);
    createEmployeeWithAttendanceForPayables($this->branchB, 'Jane', 510);

    $service = app(PayrollPeriodService::class);

    $periodA = $service->generate($this->branchA, '2026-05-25', '2026-05-29');
    $service->approve($periodA, $this->superadmin->id);

    $periodB = $service->generate($this->branchB, '2026-05-25', '2026-05-29');
    $service->approve($periodB, $this->superadmin->id);

    $response = $this->actingAs($this->superadmin)
        ->get(route('payroll.reports.branch-payables', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-06-30',
            'branch_id' => $this->branchA->id,
        ]));

    $response->assertOk();

    $results = $response->viewData('page')['props']['results'];
    $branchAResult = collect($results)->firstWhere('branch', 'Branch A');
    $branchBResult = collect($results)->firstWhere('branch', 'Branch B');

    expect($branchAResult['total_benefits'])->toBeGreaterThan(0);
    expect($branchBResult['total_benefits'])->toBe(0);

    // Should only show Branch A with values, others are zero
    expect($results)->toHaveCount(2);
});

test('deminimis is included in branch payables totals', function () {
    $employee = createEmployeeWithAttendanceForPayables($this->branchA, 'John', 510);

    $benefit = Benefit::create([
        'name' => 'Rice Allowance',
        'type' => 'perk',
        'monthly_amount' => 2000,
        'is_active' => true,
    ]);

    $employee->benefits()->attach($benefit->id, [
        'is_active' => true,
        'effective_date' => '2026-01-01',
    ]);

    $service = app(PayrollPeriodService::class);
    $period = $service->generate($this->branchA, '2026-05-25', '2026-05-29');
    $service->approve($period, $this->superadmin->id);

    $response = $this->actingAs($this->superadmin)
        ->get(route('payroll.reports.branch-payables', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-06-30',
        ]));

    $response->assertOk();

    $results = $response->viewData('page')['props']['results'];
    $branchAResult = collect($results)->firstWhere('branch', 'Branch A');

    // 2000 / 4 = 500 deminimis
    expect($branchAResult['deminimis'])->toBe(500.00);
    expect($branchAResult['total_benefits'])->toBe(
        $branchAResult['sss_employer'] +
        $branchAResult['philhealth_employer'] +
        $branchAResult['pagibig_employer'] +
        500.00,
    );
});

test('grand totals are correct across multiple branches', function () {
    createEmployeeWithAttendanceForPayables($this->branchA, 'John', 510);
    createEmployeeWithAttendanceForPayables($this->branchB, 'Jane', 510);

    $service = app(PayrollPeriodService::class);

    $periodA = $service->generate($this->branchA, '2026-05-25', '2026-05-29');
    $service->approve($periodA, $this->superadmin->id);

    $periodB = $service->generate($this->branchB, '2026-05-25', '2026-05-29');
    $service->approve($periodB, $this->superadmin->id);

    $response = $this->actingAs($this->superadmin)
        ->get(route('payroll.reports.branch-payables', [
            'date_from' => '2026-05-01',
            'date_to' => '2026-06-30',
        ]));

    $response->assertOk();

    $results = $response->viewData('page')['props']['results'];
    $grandTotal = $response->viewData('page')['props']['grand_total'];

    $sumSss = collect($results)->sum('sss_employer');
    $sumPhilHealth = collect($results)->sum('philhealth_employer');
    $sumPagibig = collect($results)->sum('pagibig_employer');
    $sumDeminimis = collect($results)->sum('deminimis');
    $sumTotal = collect($results)->sum('total_benefits');

    expect($grandTotal['sss_employer'])->toBe(round($sumSss, 2));
    expect($grandTotal['philhealth_employer'])->toBe(round($sumPhilHealth, 2));
    expect($grandTotal['pagibig_employer'])->toBe(round($sumPagibig, 2));
    expect($grandTotal['deminimis'])->toBe(round($sumDeminimis, 2));
    expect($grandTotal['total_benefits'])->toBe(round($sumTotal, 2));

    // Both branches have same employee, so total should be 2x single
    $itemA = PayrollPeriodItem::where('payroll_period_id', $periodA->id)->first();
    $itemB = PayrollPeriodItem::where('payroll_period_id', $periodB->id)->first();
    $expectedTotal = (float) ($itemA->sss_employer + $itemA->philhealth_employer + $itemA->pagibig_employer + $itemA->deminimis_earnings)
        + (float) ($itemB->sss_employer + $itemB->philhealth_employer + $itemB->pagibig_employer + $itemB->deminimis_earnings);

    expect($grandTotal['total_benefits'])->toBe(round($expectedTotal, 2));
});

test('validation requires date_from and date_to', function () {
    $response = $this->actingAs($this->superadmin)
        ->get(route('payroll.reports.branch-payables'));

    $response->assertSessionHasErrors(['date_from', 'date_to']);
});
