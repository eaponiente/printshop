<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\CompanyConfig;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
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

    $expectedSss = round($monthlySalary * 10 / 100 / 4, 2);
    expect((float) $item->sss_employer)->toBe($expectedSss);

    $expectedPhilHealth = round($monthlySalary * 0.05 * 0.50 / 4, 2);
    expect((float) $item->philhealth_employer)->toBe($expectedPhilHealth);

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

test('superadmin can view branch payables report with selected periods', function () {
    createEmployeeWithAttendanceForPayables($this->branchA, 'John', 510);

    $service = app(PayrollPeriodService::class);
    $period = $service->generate($this->branchA, '2026-05-25', '2026-05-29');
    $service->approve($period, $this->superadmin->id);

    $response = $this->actingAs($this->superadmin)
        ->get(route('payroll.reports.branch-payables', [
            'period_ids' => [$period->id],
        ]));

    $response->assertOk();

    $results = $response->viewData('page')['props']['results'];
    expect($results)->toHaveCount(1);

    $row = $results[0];
    expect($row['branch'])->toBe('Branch A');
    expect($row['sss_employee'])->toBeGreaterThan(0);
    expect($row['sss_employer'])->toBeGreaterThan(0);
    expect($row['sss_total'])->toBe(round($row['sss_employee'] + $row['sss_employer'], 2));
    expect($row['philhealth_employee'])->toBeGreaterThan(0);
    expect($row['philhealth_employer'])->toBeGreaterThan(0);
    expect($row['pagibig_employee'])->toBeGreaterThan(0);
    expect($row['pagibig_employer'])->toBeGreaterThan(0);

    $grandTotal = $response->viewData('page')['props']['grand_total'];
    expect($grandTotal)->not->toBeNull();
    expect($grandTotal['sss_total'])->toBeGreaterThan(0);
});

test('admin can view branch payables report for their own branch', function () {
    createEmployeeWithAttendanceForPayables($this->branchA, 'John', 510);

    $service = app(PayrollPeriodService::class);
    $period = $service->generate($this->branchA, '2026-05-25', '2026-05-29');
    $service->approve($period, $this->superadmin->id);

    $response = $this->actingAs($this->admin)
        ->get(route('payroll.reports.branch-payables', [
            'period_ids' => [$period->id],
        ]));

    $response->assertOk();

    $results = $response->viewData('page')['props']['results'];
    expect($results)->toHaveCount(1);
    expect($results[0]['branch'])->toBe('Branch A');
});

test('admin cannot access another branchs period even when id is injected', function () {
    createEmployeeWithAttendanceForPayables($this->branchB, 'Jane', 510);

    $service = app(PayrollPeriodService::class);
    $periodB = $service->generate($this->branchB, '2026-05-25', '2026-05-29');
    $service->approve($periodB, $this->superadmin->id);

    // Admin belongs to branchA — passing branchB period ID should return no results
    $response = $this->actingAs($this->admin)
        ->get(route('payroll.reports.branch-payables', [
            'period_ids' => [$periodB->id],
        ]));

    $response->assertOk();
    $results = $response->viewData('page')['props']['results'];
    expect($results)->toHaveCount(0);
});

test('staff cannot view branch payables report', function () {
    $response = $this->actingAs($this->staff)
        ->get(route('payroll.reports.branch-payables'));

    $response->assertForbidden();
});

test('no period_ids returns empty results with period picker data', function () {
    createEmployeeWithAttendanceForPayables($this->branchA, 'John', 510);

    $service = app(PayrollPeriodService::class);
    $period = $service->generate($this->branchA, '2026-05-25', '2026-05-29');
    $service->approve($period, $this->superadmin->id);

    $response = $this->actingAs($this->superadmin)
        ->get(route('payroll.reports.branch-payables'));

    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['results'])->toBeArray()->toHaveCount(0);
    expect($props['grand_total'])->toBeNull();
    expect($props['periods'])->toHaveCount(1);
});

test('grand totals are correct across multiple periods', function () {
    createEmployeeWithAttendanceForPayables($this->branchA, 'John', 510);
    createEmployeeWithAttendanceForPayables($this->branchB, 'Jane', 510);

    $service = app(PayrollPeriodService::class);

    $periodA = $service->generate($this->branchA, '2026-05-25', '2026-05-29');
    $service->approve($periodA, $this->superadmin->id);

    $periodB = $service->generate($this->branchB, '2026-05-25', '2026-05-29');
    $service->approve($periodB, $this->superadmin->id);

    $response = $this->actingAs($this->superadmin)
        ->get(route('payroll.reports.branch-payables', [
            'period_ids' => [$periodA->id, $periodB->id],
        ]));

    $response->assertOk();

    $props = $response->viewData('page')['props'];
    $results = $props['results'];
    $grandTotal = $props['grand_total'];

    expect($results)->toHaveCount(2);

    expect($grandTotal['sss_employee'])->toBe(round(collect($results)->sum('sss_employee'), 2));
    expect($grandTotal['sss_employer'])->toBe(round(collect($results)->sum('sss_employer'), 2));
    expect($grandTotal['sss_total'])->toBe(round(collect($results)->sum('sss_total'), 2));
    expect($grandTotal['philhealth_total'])->toBe(round(collect($results)->sum('philhealth_total'), 2));
    expect($grandTotal['pagibig_total'])->toBe(round(collect($results)->sum('pagibig_total'), 2));
});
