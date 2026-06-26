<?php

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\Holiday;
use App\Models\Payroll\LeaveRequest;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollPeriodItem;
use App\Models\Payroll\Salary;
use App\Models\Payroll\SssContributionBracket;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use Payroll\Attendance\Enums\HolidayType;
use Payroll\Attendance\Enums\PayrollPeriodStatus;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;
use Payroll\Attendance\Services\PayrollPeriodService;

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

    // Seed SSS brackets
    SssContributionBracket::create([
        'salary_min' => 0,
        'salary_max' => 20000,
        'employee_percentage' => 5,
        'employer_percentage' => 10,
        'effective_from' => '2026-01-01',
    ]);
});

function createEmployeeWithAttendance(
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

    // Create perfect attendance for 5 days (Mon-Fri)
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

// ──────────── Batch 1: Basic generation ────────────

it('allows admin to generate a payroll period for their branch', function () {
    $emp = createEmployeeWithAttendance($this->branchA, 'John');

    $response = $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $response->assertRedirect();
    $period = PayrollPeriod::first();
    expect($period)->not->toBeNull();
    expect($period->branch_id)->toBe($this->branchA->id);
    expect($period->status)->toBe(PayrollPeriodStatus::DRAFT);
    expect($period->period_start->toDateString())->toBe('2026-05-25');
    expect($period->period_end->toDateString())->toBe('2026-05-30');
});

it('allows superadmin to generate for a specific branch', function () {
    createEmployeeWithAttendance($this->branchB, 'Jane');

    $response = $this->actingAs($this->superadmin)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
            'branch_id' => $this->branchB->id,
        ]);

    $response->assertRedirect();
    $period = PayrollPeriod::first();
    expect($period->branch_id)->toBe($this->branchB->id);
});

it('blocks staff from generating', function () {
    $response = $this->actingAs($this->staffA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $response->assertForbidden();
    expect(PayrollPeriod::count())->toBe(0);
});

it('blocks duplicate period for same branch and date range', function () {
    createEmployeeWithAttendance($this->branchA, 'John');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ])
        ->assertRedirect();

    $response = $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $response->assertSessionHasErrors('error');
    expect(PayrollPeriod::count())->toBe(1);
});

it('locks attendance sheets on generation', function () {
    createEmployeeWithAttendance($this->branchA, 'John');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $sheets = AttendanceSheet::whereBetween('date', ['2026-05-25', '2026-05-30'])->get();
    expect($sheets->count())->toBeGreaterThan(0);
    expect($sheets->every(fn ($s) => $s->locked_at !== null))->toBeTrue();
});

it('creates payroll period items for each active employee', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp1');
    createEmployeeWithAttendance($this->branchA, 'Emp2', 550);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    expect(PayrollPeriodItem::count())->toBe(2);
});

it('excludes inactive employees', function () {
    createEmployeeWithAttendance($this->branchA, 'Active');
    Employee::create([
        'first_name' => 'Inactive',
        'last_name' => 'Test',
        'branch_id' => $this->branchA->id,
        'hire_date' => '2026-01-01',
        'position' => 'regular',
        'status' => 'terminated',
        'current_daily_rate' => 510,
    ]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $items = PayrollPeriodItem::with('employee')->get();
    expect($items->count())->toBe(1);
    expect($items->first()->employee->first_name)->toBe('Active');
});

it('excludes superadmin employees', function () {
    createEmployeeWithAttendance($this->branchA, 'AdminEmp');
    $superadminEmp = createEmployeeWithAttendance($this->branchA, 'SuperAdminEmp');

    $this->superadmin->update(['employee_id' => $superadminEmp->id]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $items = PayrollPeriodItem::with('employee')->get();
    expect($items->count())->toBe(1);
    expect($items->first()->employee->first_name)->toBe('AdminEmp');
});

// ──────────── Batch 2: Validation ────────────

it('validates period_end must be after period_start', function () {
    $response = $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-30',
            'period_end' => '2026-05-25',
        ]);

    $response->assertSessionHasErrors('period_end');
});

it('requires branch_id for superadmin', function () {
    $response = $this->actingAs($this->superadmin)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $response->assertSessionHasErrors('branch_id');
});

// ──────────── Batch 3: Computation accuracy ────────────

it('computes gross pay as sum of daily wages', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp', 510, [], 5);
    // 5 days × ₱510 = ₱2,550

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $item = PayrollPeriodItem::first();
    expect((float) $item->gross_pay)->toBe(2550.0);
    expect($item->total_regular_days)->toBe(5);
});

it('computes SSS deduction only when employee has sss_number', function () {
    $withSSS = createEmployeeWithAttendance($this->branchA, 'WithSSS', 510, ['sss' => '123', 'phic' => null, 'pagibig' => null]);
    $withoutSSS = createEmployeeWithAttendance($this->branchA, 'NoSSS', 510, ['sss' => null, 'phic' => null, 'pagibig' => null]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $itemWith = PayrollPeriodItem::where('employee_id', $withSSS->id)->first();
    $itemWithout = PayrollPeriodItem::where('employee_id', $withoutSSS->id)->first();

    // SSS: 510 × 26 = 13260, 13260 × 5% / 100 / 4 = 165.75
    expect((float) $itemWith->sss_deduction)->toBeGreaterThan(0);
    expect((float) $itemWithout->sss_deduction)->toEqual(0.0);
});

it('computes Pag-IBIG only when employee has pagibig_number', function () {
    $with = createEmployeeWithAttendance($this->branchA, 'With', 510, ['sss' => null, 'phic' => null, 'pagibig' => '789']);
    $without = createEmployeeWithAttendance($this->branchA, 'No', 510, ['sss' => null, 'phic' => null, 'pagibig' => null]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $itemWith = PayrollPeriodItem::where('employee_id', $with->id)->first();
    $itemWithout = PayrollPeriodItem::where('employee_id', $without->id)->first();

    expect((float) $itemWith->pagibig_deduction)->toEqual(25.0);
    expect((float) $itemWithout->pagibig_deduction)->toEqual(0.0);
});

it('computes PhilHealth only when employee has philhealth_number', function () {
    $with = createEmployeeWithAttendance($this->branchA, 'With', 510, ['sss' => null, 'phic' => '456', 'pagibig' => null]);
    $without = createEmployeeWithAttendance($this->branchA, 'No', 510, ['sss' => null, 'phic' => null, 'pagibig' => null]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $itemWith = PayrollPeriodItem::where('employee_id', $with->id)->first();
    $itemWithout = PayrollPeriodItem::where('employee_id', $without->id)->first();

    expect((float) $itemWith->philhealth_deduction)->toBeGreaterThan(0);
    expect((float) $itemWithout->philhealth_deduction)->toEqual(0.0);
});

it('deducts cash advance up to net receivable', function () {
    $emp = createEmployeeWithAttendance($this->branchA, 'Emp', 510, ['sss' => null, 'phic' => null, 'pagibig' => null]);

    CashAdvance::create([
        'employee_id' => $emp->id,
        'amount' => 1000,
        'remaining_balance' => 1000,
        'reason' => 'Test',
        'status' => 'approved',
    ]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $item = PayrollPeriodItem::where('employee_id', $emp->id)->first();
    // Gross = 510 × 5 = 2550. No govt deductions (no IDs). CA = min(1000, 2550) = 1000
    expect((float) $item->ca_deduction)->toBe(1000.0);
    expect((float) $item->net_pay)->toBe(1550.0);

    $ca = CashAdvance::first();
    expect((float) $ca->remaining_balance)->toBe(0.0);
    expect($ca->status)->toBe('paid');
});

// ──────────── Batch 4: Edge cases ────────────

it('gives no holiday pay when employee does not work on regular holiday', function () {
    $emp = createEmployeeWithAttendance($this->branchA, 'Emp', 510, [], 4);

    Holiday::create([
        'name' => 'Test Holiday',
        'date' => '2026-05-29',
        'type' => HolidayType::REGULAR,
    ]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $sheet = AttendanceSheet::where('employee_id', $emp->id)
        ->where('date', '2026-05-29')
        ->first();

    expect($sheet)->not->toBeNull();
    expect($sheet->holiday_pay_percent)->toBe(0);
    expect((float) $sheet->holiday_pay)->toBe(0.0);

    $item = PayrollPeriodItem::where('employee_id', $emp->id)->first();
    expect($item->holiday_pay_days)->toBe(0);
    expect((float) $item->holiday_pay)->toBe(0.0);
});

it('gives double pay when employee works on regular holiday', function () {
    $emp = Employee::create([
        'first_name' => 'HolidayWorker',
        'last_name' => 'Test',
        'branch_id' => $this->branchA->id,
        'hire_date' => '2026-01-05',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 510,
    ]);

    EmployeeSchedule::create([
        'employee_id' => $emp->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0, 6],
        'effective_from' => '2026-05-25',
        'is_active' => true,
    ]);

    Holiday::create([
        'name' => 'Test Holiday',
        'date' => '2026-05-29',
        'type' => HolidayType::REGULAR,
    ]);

    TimeLog::create([
        'employee_id' => $emp->id,
        'timestamp' => Carbon::parse('2026-05-29 08:00'),
        'type' => PunchType::IN,
        'source' => PunchSource::SELF_SERVICE,
    ]);
    TimeLog::create([
        'employee_id' => $emp->id,
        'timestamp' => Carbon::parse('2026-05-29 17:00'),
        'type' => PunchType::OUT,
        'source' => PunchSource::SELF_SERVICE,
    ]);

    app(AttendanceService::class)->processDailyAttendance($emp, '2026-05-29');

    $sheet = AttendanceSheet::where('employee_id', $emp->id)
        ->where('date', '2026-05-29')
        ->first();

    expect($sheet)->not->toBeNull();
    expect($sheet->is_present)->toBeTrue();
    expect($sheet->holiday_type)->toBe('regular');
    expect($sheet->holiday_pay_percent)->toBe(200);

    $expectedHolidayPay = round(510 * (200 - 100) / 100, 2);
    expect((float) $sheet->holiday_pay)->toBe($expectedHolidayPay);

    $expectedDailyWage = round(510.0 - 0 - 0 - 0 + 0 + $expectedHolidayPay, 2);
    expect((float) $sheet->daily_wage)->toBe($expectedDailyWage);
});

it('computes holiday pay as 0 when employee was absent day before', function () {
    $emp = createEmployeeWithAttendance($this->branchA, 'Emp', 510, [], 3);

    Holiday::create([
        'name' => 'Test Holiday',
        'date' => '2026-05-29',
        'type' => HolidayType::REGULAR,
    ]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $sheet = AttendanceSheet::where('employee_id', $emp->id)
        ->where('date', '2026-05-29')
        ->first();

    expect($sheet)->not->toBeNull();
    expect($sheet->holiday_pay_percent)->toBe(0);
    expect((float) $sheet->holiday_pay)->toBe(0.0);

    $item = PayrollPeriodItem::where('employee_id', $emp->id)->first();
    expect($item->holiday_pay_days)->toBe(0);
    expect((float) $item->holiday_pay)->toBe(0.0);
});

// ──────────── Batch 5: Leave ────────────

it('processes full-day paid leave', function () {
    $emp = createEmployeeWithAttendance($this->branchA, 'LeavePaid', 510, [], 4);

    LeaveRequest::create([
        'employee_id' => $emp->id,
        'date' => '2026-05-29',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Vacation',
        'status' => 'approved',
    ]);

    app(AttendanceService::class)->processDailyAttendance($emp, '2026-05-29');

    $sheet = AttendanceSheet::where('employee_id', $emp->id)
        ->where('date', '2026-05-29')
        ->first();

    expect($sheet)->not->toBeNull();
    expect($sheet->is_present)->toBeTrue();
    expect($sheet->absence_type)->toBe('approved_leave');
    expect($sheet->leave_type)->toBe('vacation');
    expect($sheet->leave_duration)->toBe('full_day');
    expect($sheet->leave_is_paid)->toBeTrue();
    expect((float) $sheet->leave_hours_credited)->toBe(8.0);
    expect((float) $sheet->daily_wage)->toBe(510.0);
    expect($sheet->late_minutes)->toBe(0);
    expect($sheet->overtime_minutes)->toBe(0);
    expect((float) $sheet->holiday_pay)->toBe(0.0);
});

it('processes full-day unpaid leave', function () {
    $emp = createEmployeeWithAttendance($this->branchA, 'LeaveUnpaid', 510, [], 4);

    LeaveRequest::create([
        'employee_id' => $emp->id,
        'date' => '2026-05-29',
        'leave_type' => 'unpaid',
        'duration' => 'full_day',
        'is_paid' => false,
        'reason' => 'Personal',
        'status' => 'approved',
    ]);

    app(AttendanceService::class)->processDailyAttendance($emp, '2026-05-29');

    $sheet = AttendanceSheet::where('employee_id', $emp->id)
        ->where('date', '2026-05-29')
        ->first();

    expect($sheet)->not->toBeNull();
    expect($sheet->is_present)->toBeTrue();
    expect($sheet->absence_type)->toBe('approved_leave');
    expect($sheet->leave_is_paid)->toBeFalse();
    expect($sheet->leave_duration)->toBe('full_day');
    expect((float) $sheet->daily_wage)->toBe(0.0);
});

it('includes leave_paid_days in payroll period item', function () {
    $emp = createEmployeeWithAttendance($this->branchA, 'LeavePayslip', 510, [], 4);

    LeaveRequest::create([
        'employee_id' => $emp->id,
        'date' => '2026-05-29',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Vacation',
        'status' => 'approved',
    ]);

    // Process leave manually before payroll generation
    app(AttendanceService::class)->processDailyAttendance($emp, '2026-05-29');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $item = PayrollPeriodItem::where('employee_id', $emp->id)->first();
    expect($item->leave_paid_days)->toBe(1);
    expect((float) $item->gross_pay)->toBe(2550.0); // 4 work + 1 paid leave = 5 days
});

it('handles branch with no employees', function () {
    $response = $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $response->assertRedirect();
    expect(PayrollPeriod::count())->toBe(1);
    expect(PayrollPeriodItem::count())->toBe(0);
});

it('handles period with no attendance sheets', function () {
    Employee::create([
        'first_name' => 'New',
        'last_name' => 'Hire',
        'branch_id' => $this->branchA->id,
        'hire_date' => '2026-06-01',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 510,
    ]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $item = PayrollPeriodItem::first();
    expect($item)->not->toBeNull();
    expect($item->total_regular_days)->toBe(0);
    expect((float) $item->gross_pay)->toBe(0.0);
});

it('net_pay never goes below zero', function () {
    $emp = createEmployeeWithAttendance($this->branchA, 'Emp', 510, ['sss' => '123', 'phic' => '456', 'pagibig' => '789']);

    // Create large CA that would go negative
    CashAdvance::create([
        'employee_id' => $emp->id,
        'amount' => 100000,
        'remaining_balance' => 100000,
        'reason' => 'Huge CA',
        'status' => 'approved',
    ]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $item = PayrollPeriodItem::where('employee_id', $emp->id)->first();
    expect((float) $item->net_pay)->toBeGreaterThanOrEqual(0);
});

it('allows superadmin to approve a draft period', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $period = PayrollPeriod::first();
    expect($period->status)->toBe(PayrollPeriodStatus::DRAFT);

    $this->actingAs($this->superadmin)
        ->post(route('payroll.periods.approve', $period))
        ->assertRedirect();

    $period->refresh();
    expect($period->status)->toBe(PayrollPeriodStatus::APPROVED);
    expect($period->approved_by)->toBe($this->superadmin->id);
    expect($period->approved_at)->not->toBeNull();
});

it('allows admin to approve a period in their own branch', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $period = PayrollPeriod::first();
    expect($period->status)->toBe(PayrollPeriodStatus::DRAFT);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.approve', $period))
        ->assertRedirect();

    $period->refresh();
    expect($period->status)->toBe(PayrollPeriodStatus::APPROVED);
    expect($period->approved_by)->toBe($this->adminA->id);
});

it('blocks admin from approving a period in another branch', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp');

    $adminB = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branchB->id,
    ]);

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $period = PayrollPeriod::first();

    $this->actingAs($adminB)
        ->post(route('payroll.periods.approve', $period))
        ->assertForbidden();

    $period->refresh();
    expect($period->status)->toBe(PayrollPeriodStatus::DRAFT);
});

it('only superadmin can void a period', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $period = PayrollPeriod::first();
    $this->actingAs($this->superadmin)
        ->post(route('payroll.periods.approve', $period));

    // Admin cannot void
    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.void', $period))
        ->assertForbidden();

    // Superadmin can void
    $this->actingAs($this->superadmin)
        ->post(route('payroll.periods.void', $period))
        ->assertRedirect();

    $period->refresh();
    expect($period->status)->toBe(PayrollPeriodStatus::VOIDED);

    // Sheets unlocked
    $sheets = AttendanceSheet::whereNotNull('locked_at')->get();
    expect($sheets->count())->toBe(0);
});

it('prevents voiding a draft period', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $period = PayrollPeriod::first();
    expect($period->status)->toBe(PayrollPeriodStatus::DRAFT);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Draft periods cannot be voided.');
    app(PayrollPeriodService::class)->void($period);
});

it('prevents approving an already approved period', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $period = PayrollPeriod::first();
    $this->actingAs($this->superadmin)
        ->post(route('payroll.periods.approve', $period))
        ->assertRedirect();

    $period->refresh();
    expect($period->status)->toBe(PayrollPeriodStatus::APPROVED);

    // Second approve should throw
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Only draft periods can be approved.');
    app(PayrollPeriodService::class)->approve($period, $this->adminA->id);
});

// ──────────── Batch 5: Deletion ────────────

it('allows admin to delete draft period in their branch', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $period = PayrollPeriod::first();
    expect($period->status)->toBe(PayrollPeriodStatus::DRAFT);

    $this->actingAs($this->adminA)
        ->delete(route('payroll.periods.destroy', $period))
        ->assertRedirect(route('payroll.periods.index'));

    expect(PayrollPeriod::count())->toBe(0);
    expect(PayrollPeriodItem::count())->toBe(0);
});

it('allows superadmin to delete any draft period', function () {
    createEmployeeWithAttendance($this->branchB, 'Emp');

    $this->actingAs($this->superadmin)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
            'branch_id' => $this->branchB->id,
        ]);

    $period = PayrollPeriod::first();

    $this->actingAs($this->superadmin)
        ->delete(route('payroll.periods.destroy', $period))
        ->assertRedirect(route('payroll.periods.index'));

    expect(PayrollPeriod::count())->toBe(0);
});

it('prevents deletion of approved periods', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $period = PayrollPeriod::first();
    $this->actingAs($this->superadmin)
        ->post(route('payroll.periods.approve', $period));

    $period->refresh();
    expect($period->status)->toBe(PayrollPeriodStatus::APPROVED);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Only draft periods can be deleted.');
    app(PayrollPeriodService::class)->delete($period);
});

it('denies staff from deleting', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    $period = PayrollPeriod::first();

    $this->actingAs($this->staffA)
        ->delete(route('payroll.periods.destroy', $period))
        ->assertForbidden();

    expect(PayrollPeriod::count())->toBe(1);
});

it('admin cannot delete draft period in other branch', function () {
    createEmployeeWithAttendance($this->branchB, 'Emp');

    $this->actingAs($this->superadmin)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
            'branch_id' => $this->branchB->id,
        ]);

    $period = PayrollPeriod::first();

    $this->actingAs($this->adminA)
        ->delete(route('payroll.periods.destroy', $period))
        ->assertForbidden();

    expect(PayrollPeriod::count())->toBe(1);
});

it('unlocks attendance sheets on draft deletion', function () {
    createEmployeeWithAttendance($this->branchA, 'Emp');

    $this->actingAs($this->adminA)
        ->post(route('payroll.periods.generate'), [
            'period_start' => '2026-05-25',
            'period_end' => '2026-05-30',
        ]);

    // Verify sheets are locked after generation
    $lockedCount = AttendanceSheet::whereBetween('date', ['2026-05-25', '2026-05-30'])
        ->whereNotNull('locked_at')
        ->count();
    expect($lockedCount)->toBeGreaterThan(0);

    $period = PayrollPeriod::first();

    $this->actingAs($this->adminA)
        ->delete(route('payroll.periods.destroy', $period))
        ->assertRedirect(route('payroll.periods.index'));

    // Verify sheets are unlocked after deletion
    $unlockedCount = AttendanceSheet::whereBetween('date', ['2026-05-25', '2026-05-30'])
        ->whereNull('locked_at')
        ->count();
    expect($unlockedCount)->toBeGreaterThan(0);
});

// ──────────── Batch: Net pay consistency ────────────

it('net_pay equals gross_pay + deminimis minus statutory and cash advance only', function () {
    // Late employee whose daily_wage already absorbs the late deduction.
    $emp = Employee::create([
        'first_name' => 'Late',
        'last_name' => 'Linus',
        'branch_id' => $this->branchA->id,
        'hire_date' => '2026-01-05',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 510,
        'sss_number' => '123',
        'philhealth_number' => '456',
        'pagibig_number' => '789',
    ]);
    Salary::createForEmployee($emp, 510, '2026-01-05', 'Initial');
    EmployeeSchedule::create([
        'employee_id' => $emp->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0, 6],
        'effective_from' => '2026-05-25',
        'is_active' => true,
    ]);

    $dates = ['2026-05-25', '2026-05-26', '2026-05-27', '2026-05-28', '2026-05-29'];
    foreach ($dates as $i => $date) {
        AttendanceSheet::create([
            'employee_id' => $emp->id,
            'date' => $date,
            'schedule_start_time' => '08:00',
            'schedule_end_time' => '17:00',
            'rest_days' => [0, 6],
            'daily_rate' => 510,
            'late_minutes' => $i === 0 ? 30 : 0,
            'late_deduction' => $i === 0 ? 210.63 : 0,
            'hours_worked' => 8,
            'daily_wage' => $i === 0 ? 299.37 : 510,
            'is_present' => true,
        ]);
    }

    $service = app(PayrollPeriodService::class);
    $period = $service->generate($this->branchA, '2026-05-25', '2026-05-30');
    $item = PayrollPeriodItem::where('payroll_period_id', $period->id)->first();

    $statutoryAndCa =
        (float) $item->sss_deduction
        + (float) $item->philhealth_deduction
        + (float) $item->pagibig_deduction
        + (float) $item->ca_deduction;

    $expectedNet = round(
        (float) $item->gross_pay + (float) $item->deminimis_earnings - $statutoryAndCa,
        2,
    );

    expect((float) $item->net_pay)->toBe($expectedNet);
    // Late deduction is NOT part of the post-gross subtraction — it's baked into gross_pay.
    expect((float) $item->late_deduction)->toBeGreaterThan(0);
    expect((float) $item->gross_pay)->toBeLessThan(510 * 5);
});

// ──────────── Batch: Query count guard for generate() ────────────

it('generate runs a bounded number of queries regardless of employee count', function () {
    // Seed 10 employees with 5 days of attendance each.
    for ($n = 0; $n < 10; $n++) {
        createEmployeeWithAttendance($this->branchA, "Emp{$n}");
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    app(PayrollPeriodService::class)->generate($this->branchA, '2026-05-25', '2026-05-30');

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Per-employee item insert + statutory bracket lookups + ledger updates are unavoidable.
    // Pre-refactor this would have been well over 200. We allow generous headroom but
    // assert the order of magnitude has dropped.
    expect($count)->toBeLessThan(150);
});
