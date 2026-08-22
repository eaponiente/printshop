<?php

use App\Models\Branch;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\CashAdvanceDeduction;
use App\Models\Payroll\CompanyConfig;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use App\Models\Payroll\Holiday;
use App\Models\Payroll\LeaveRequest;
use App\Models\Payroll\OvertimeRequest;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollPeriodItem;
use App\Models\Payroll\TimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Payroll\Attendance\Enums\HolidayType;
use Payroll\Attendance\Enums\PayrollPeriodStatus;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;
use Payroll\Attendance\Services\PayrollPeriodService;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['name' => 'Test Branch']);

    $this->superadmin = User::factory()->create([
        'role' => 'superadmin',
        'branch_id' => null,
    ]);

    $this->admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branch->id,
    ]);

    $this->staff = User::factory()->create([
        'role' => 'staff',
        'branch_id' => $this->branch->id,
    ]);

    $this->employee = Employee::create([
        'first_name' => 'Pay',
        'last_name' => 'Slipowski',
        'branch_id' => $this->branch->id,
        'hire_date' => '2026-01-05',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 510,
        'sss_number' => 'SSS-1',
        'philhealth_number' => 'PHIC-1',
        'pagibig_number' => 'HDMF-1',
    ]);

    $this->staff->update(['employee_id' => $this->employee->id]);

    $this->period = PayrollPeriod::create([
        'branch_id' => $this->branch->id,
        'period_start' => '2026-05-25',
        'period_end' => '2026-05-30',
        'status' => PayrollPeriodStatus::DRAFT,
    ]);

    $this->item = PayrollPeriodItem::create([
        'payroll_period_id' => $this->period->id,
        'employee_id' => $this->employee->id,
        'total_regular_days' => 5,
        'absent_days' => 0,
        'total_late_minutes' => 0,
        'late_deduction' => 0,
        'total_undertime_minutes' => 0,
        'undertime_deduction' => 0,
        'total_overtime_minutes' => 0,
        'overtime_pay' => 0,
        'holiday_pay_days' => 0,
        'holiday_pay' => 0,
        'leave_paid_days' => 0,
        'fine_deduction' => 0,
        'gross_pay' => 2550,
        'deminimis_earnings' => 0,
        'sss_deduction' => 100,
        'sss_employer' => 200,
        'philhealth_deduction' => 50,
        'philhealth_employer' => 50,
        'pagibig_deduction' => 25,
        'pagibig_employer' => 25,
        'ca_deduction' => 0,
        'net_pay' => 2375,
        'daily_rate' => 510,
        'sss_bracket' => 1,
    ]);
});

it('hides employer contributions from staff viewing their own payslip', function () {
    $this->actingAs($this->staff)
        ->get(route('payroll.payslip', [$this->period->id, $this->item->id]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('payroll/payroll/payslip')
                ->where('viewerCanSeeEmployer', false)
        );
});

it('shows employer contributions to admin', function () {
    $this->actingAs($this->admin)
        ->get(route('payroll.payslip', [$this->period->id, $this->item->id]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('payroll/payroll/payslip')
                ->where('viewerCanSeeEmployer', true)
        );
});

it('shows employer contributions to superadmin', function () {
    $this->actingAs($this->superadmin)
        ->get(route('payroll.payslip', [$this->period->id, $this->item->id]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('viewerCanSeeEmployer', true)
        );
});

it('passes the company name from CompanyConfig', function () {
    CompanyConfig::updateOrCreate(
        ['key' => 'company_name'],
        ['value' => 'Acme Print Co.', 'label' => 'Company Name'],
    );

    $this->actingAs($this->admin)
        ->get(route('payroll.payslip', [$this->period->id, $this->item->id]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('companyName', 'Acme Print Co.')
        );
});

it('itemizes each cash advance deduction on the payslip with its reason and remaining balance', function () {
    $olderCa = CashAdvance::create([
        'employee_id' => $this->employee->id,
        'amount' => 50,
        'remaining_balance' => 0,
        'reason' => 'Medical',
        'status' => 'paid',
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);
    $newerCa = CashAdvance::create([
        'employee_id' => $this->employee->id,
        'amount' => 1000,
        'remaining_balance' => 955,
        'reason' => 'Tuition',
        'status' => 'approved',
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    $this->item->update(['ca_deduction' => 95]);
    CashAdvanceDeduction::create([
        'payroll_period_item_id' => $this->item->id,
        'cash_advance_id' => $olderCa->id,
        'amount' => 50,
    ]);
    CashAdvanceDeduction::create([
        'payroll_period_item_id' => $this->item->id,
        'cash_advance_id' => $newerCa->id,
        'amount' => 45,
    ]);

    $this->actingAs($this->admin)
        ->get(route('payroll.payslip', [$this->period->id, $this->item->id]))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('item.cash_advance_deductions', 2)
                ->where('item.cash_advance_deductions.0.cash_advance.reason', 'Medical')
                ->where('item.cash_advance_deductions.1.cash_advance.reason', 'Tuition')
                ->where('item.cash_advance_deductions.1.cash_advance.remaining_balance', '955.00')
        );
});

it('blocks staff from viewing another employees payslip', function () {
    $otherEmp = Employee::create([
        'first_name' => 'Other',
        'last_name' => 'Person',
        'branch_id' => $this->branch->id,
        'hire_date' => '2026-01-05',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 510,
    ]);

    $otherItem = PayrollPeriodItem::create([
        'payroll_period_id' => $this->period->id,
        'employee_id' => $otherEmp->id,
        'total_regular_days' => 5,
        'absent_days' => 0,
        'total_late_minutes' => 0,
        'late_deduction' => 0,
        'total_undertime_minutes' => 0,
        'undertime_deduction' => 0,
        'total_overtime_minutes' => 0,
        'overtime_pay' => 0,
        'holiday_pay_days' => 0,
        'holiday_pay' => 0,
        'leave_paid_days' => 0,
        'fine_deduction' => 0,
        'gross_pay' => 2550,
        'deminimis_earnings' => 0,
        'sss_deduction' => 0,
        'philhealth_deduction' => 0,
        'pagibig_deduction' => 0,
        'ca_deduction' => 0,
        'net_pay' => 2550,
        'daily_rate' => 510,
        'sss_bracket' => 1,
    ]);

    $this->actingAs($this->staff)
        ->get(route('payroll.payslip', [$this->period->id, $otherItem->id]))
        ->assertForbidden();
});

// ──────────── Basic Pay back-solve (regression for the itemized-earnings bug) ────────────
//
// `total_regular_days` deliberately excludes any day carrying a `holiday_type`
// or `is_rest_day` (PayrollPeriodService::generateItemForEmployee), but that
// day's base pay is still inside `gross_pay` via `daily_wage`. The payslip
// used to compute "Basic Pay" as `daily_rate * total_regular_days`, which
// silently dropped a worked holiday/rest day's base pay from the itemized
// breakdown while it stayed inside GROSS PAY — the lines no longer summed to
// gross. `payslip.tsx::EarningsCard` now back-solves Basic Pay from the
// canonical `gross_pay` instead. These tests reproduce that end-to-end and
// assert the line items always reconcile.

function payslipPunchFullDay(int $employeeId, string $date): void
{
    foreach ([
        ['08:00', PunchType::IN],
        ['12:00', PunchType::LUNCH_OUT],
        ['13:00', PunchType::LUNCH_IN],
        ['17:00', PunchType::OUT],
    ] as [$time, $type]) {
        TimeLog::create([
            'employee_id' => $employeeId,
            'timestamp' => Carbon::parse("{$date} {$time}"),
            'type' => $type,
            'source' => PunchSource::SELF_SERVICE,
        ]);
    }
}

// Mirrors payslip.tsx's EarningsCard back-solve — kept in sync with that
// comment. `$i` is the raw `item` prop array from the Inertia payslip page.
function payslipBackSolveBasicPay(array $i): float
{
    $leavePay = (float) $i['daily_rate'] * ((int) ($i['leave_paid_days'] ?? 0));

    return (float) $i['gross_pay']
        - (float) $i['overtime_pay']
        - (float) $i['holiday_pay']
        - (float) $i['incentive']
        + (float) $i['late_deduction']
        + (float) $i['undertime_deduction']
        + (float) $i['fine_deduction']
        - $leavePay;
}

function payslipInertiaProps(TestResponse $response): array
{
    $page = json_decode(json_encode($response->viewData('page')), true);

    return $page['props'];
}

it('reconciles basic pay to gross pay when the period includes a worked special holiday and a mid-week incentive', function () {
    $branch = Branch::factory()->create(['name' => 'Holiday Payslip Branch']);
    $admin = User::factory()->create(['role' => 'admin', 'branch_id' => $branch->id]);

    $employee = Employee::create([
        'first_name' => 'Holiday',
        'last_name' => 'Worker',
        'branch_id' => $branch->id,
        'hire_date' => '2026-01-01',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 550,
    ]);

    EmployeeSchedule::create([
        'employee_id' => $employee->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0], // Sunday only — Saturday is a normal work day
        'effective_from' => '2026-05-01',
        'is_active' => true,
    ]);

    // Last day of the period is a worked SPECIAL holiday (130% pay).
    Holiday::create([
        'name' => 'Test Special Holiday',
        'date' => '2026-05-30',
        'type' => HolidayType::SPECIAL,
    ]);

    $service = app(AttendanceService::class);

    $days = ['2026-05-25', '2026-05-26', '2026-05-27', '2026-05-28', '2026-05-29', '2026-05-30'];
    foreach ($days as $date) {
        payslipPunchFullDay($employee->id, $date);
        $service->processDailyAttendance($employee, $date);
    }

    // 2h approved overtime on an ordinary day — exercises the overtime_pay
    // term in the back-solve formula.
    OvertimeRequest::create([
        'employee_id' => $employee->id,
        'date' => '2026-05-26',
        'start_time' => '2026-05-26 17:00:00',
        'end_time' => '2026-05-26 19:00:00',
        'shift_type' => 'regular_day',
        'reason' => 'Rush order',
        'status' => 'approved',
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);
    $service->processDailyAttendance($employee, '2026-05-26');

    // Incentive on day 5 (2026-05-29, Friday).
    $this->actingAs($admin)
        ->patch(route('payroll.attendance.incentive.update', $employee), [
            'date' => '2026-05-29',
            'incentive' => 715,
        ])
        ->assertRedirect();

    $period = app(PayrollPeriodService::class)->generate($branch, '2026-05-25', '2026-05-30');
    $item = $period->items()->where('employee_id', $employee->id)->first();

    // The worked holiday is deliberately excluded from total_regular_days.
    expect($item->total_regular_days)->toBe(5);

    $response = $this->actingAs($admin)
        ->get(route('payroll.payslip', [$period->id, $item->id]))
        ->assertOk();

    $props = payslipInertiaProps($response);

    // All 6 days were actually worked and earn base pay.
    expect($props['basicPayDays'])->toBe(6);

    $basicPay = payslipBackSolveBasicPay($props['item']);

    expect(round($basicPay, 2))->toBe(3300.00); // 550 x 6, not the old 550 x 5 = 2750.00

    // The itemized earnings always sum back to GROSS PAY.
    $leavePay = (float) $props['item']['daily_rate'] * ((int) $props['item']['leave_paid_days']);
    $reconciled = $basicPay
        + (float) $props['item']['overtime_pay']
        + (float) $props['item']['holiday_pay']
        + (float) $props['item']['incentive']
        - (float) $props['item']['late_deduction']
        - (float) $props['item']['undertime_deduction']
        - (float) $props['item']['fine_deduction']
        + $leavePay;

    expect(round($reconciled, 2))->toBe(round((float) $props['item']['gross_pay'], 2));
});

it('includes a worked rest day in basic pay and basicPayDays even though total_regular_days excludes it', function () {
    $branch = Branch::factory()->create(['name' => 'Rest Day Payslip Branch']);
    $admin = User::factory()->create(['role' => 'admin', 'branch_id' => $branch->id]);

    $employee = Employee::create([
        'first_name' => 'Rest',
        'last_name' => 'Worker',
        'branch_id' => $branch->id,
        'hire_date' => '2026-01-01',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 600,
    ]);

    EmployeeSchedule::create([
        'employee_id' => $employee->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0, 6], // Sunday + Saturday
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);

    $sunday = '2026-06-21'; // a Sunday, and a configured rest day

    payslipPunchFullDay($employee->id, $sunday);
    app(AttendanceService::class)->processDailyAttendance($employee, $sunday);

    $period = app(PayrollPeriodService::class)->generate($branch, $sunday, $sunday);
    $item = $period->items()->where('employee_id', $employee->id)->first();

    // Worked rest day is excluded from total_regular_days...
    expect($item->total_regular_days)->toBe(0);
    expect((float) $item->gross_pay)->toBe(600.0);

    $response = $this->actingAs($admin)
        ->get(route('payroll.payslip', [$period->id, $item->id]))
        ->assertOk();

    $props = payslipInertiaProps($response);

    // ...but still counts as a paid day, and its base pay is still basic pay.
    expect($props['basicPayDays'])->toBe(1);
    expect(round(payslipBackSolveBasicPay($props['item']), 2))->toBe(600.00);
});

it('gives paid leave its own line without double-counting it into basic pay', function () {
    $branch = Branch::factory()->create(['name' => 'Leave Payslip Branch']);
    $admin = User::factory()->create(['role' => 'admin', 'branch_id' => $branch->id]);

    $employee = Employee::create([
        'first_name' => 'Leave',
        'last_name' => 'Taker',
        'branch_id' => $branch->id,
        'hire_date' => '2026-01-01',
        'position' => 'regular',
        'status' => 'active',
        'current_daily_rate' => 500,
    ]);

    EmployeeSchedule::create([
        'employee_id' => $employee->id,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'rest_days' => [0, 6],
        'effective_from' => '2026-05-01',
        'is_active' => true,
    ]);

    $service = app(AttendanceService::class);

    // 4 ordinary worked days.
    foreach (['2026-05-25', '2026-05-26', '2026-05-27', '2026-05-28'] as $date) {
        payslipPunchFullDay($employee->id, $date);
        $service->processDailyAttendance($employee, $date);
    }

    // 1 paid full-day leave.
    LeaveRequest::create([
        'employee_id' => $employee->id,
        'date' => '2026-05-29',
        'leave_type' => 'vacation',
        'duration' => 'full_day',
        'is_paid' => true,
        'reason' => 'Vacation',
        'status' => 'approved',
    ]);
    $service->processDailyAttendance($employee, '2026-05-29');

    $period = app(PayrollPeriodService::class)->generate($branch, '2026-05-25', '2026-05-29');
    $item = $period->items()->where('employee_id', $employee->id)->first();

    expect($item->leave_paid_days)->toBe(1);
    expect((float) $item->gross_pay)->toBe(2500.0); // 4 worked + 1 paid leave, 500 each

    $response = $this->actingAs($admin)
        ->get(route('payroll.payslip', [$period->id, $item->id]))
        ->assertOk();

    $props = payslipInertiaProps($response);

    // Leave day is not a "basic pay" day — it has its own line item.
    expect($props['basicPayDays'])->toBe(4);
    expect(round(payslipBackSolveBasicPay($props['item']), 2))->toBe(2000.00); // 4 x 500, not 5 x 500
});

it('always reconciles the earnings line items to gross pay, for arbitrary combinations of OT, holiday pay, incentive, leave and penalties', function () {
    $variants = [
        // Plain: nothing but basic pay.
        [
            'total_regular_days' => 5, 'total_overtime_minutes' => 0, 'overtime_pay' => 0,
            'holiday_pay_days' => 0, 'holiday_pay' => 0, 'incentive' => 0, 'leave_paid_days' => 0,
            'total_late_minutes' => 0, 'late_deduction' => 0, 'total_undertime_minutes' => 0,
            'undertime_deduction' => 0, 'fine_deduction' => 0, 'gross_pay' => 2550,
        ],
        // OT + holiday + incentive + penalties + leave, all at once.
        [
            'total_regular_days' => 3, 'total_overtime_minutes' => 120, 'overtime_pay' => 187.50,
            'holiday_pay_days' => 1, 'holiday_pay' => 76.50, 'incentive' => 40, 'leave_paid_days' => 1,
            'total_late_minutes' => 10, 'late_deduction' => 50, 'total_undertime_minutes' => 15,
            'undertime_deduction' => 30, 'fine_deduction' => 20, 'gross_pay' => 3060.50,
        ],
        // Only a worked holiday, no other days.
        [
            'total_regular_days' => 0, 'total_overtime_minutes' => 0, 'overtime_pay' => 0,
            'holiday_pay_days' => 1, 'holiday_pay' => 153, 'incentive' => 0, 'leave_paid_days' => 0,
            'total_late_minutes' => 0, 'late_deduction' => 0, 'total_undertime_minutes' => 0,
            'undertime_deduction' => 0, 'fine_deduction' => 0, 'gross_pay' => 663,
        ],
    ];

    foreach ($variants as $variant) {
        $this->item->update($variant);

        $response = $this->actingAs($this->admin)
            ->get(route('payroll.payslip', [$this->period->id, $this->item->id]))
            ->assertOk();

        $props = payslipInertiaProps($response);
        $i = $props['item'];

        $basicPay = payslipBackSolveBasicPay($i);
        $leavePay = (float) $i['daily_rate'] * ((int) $i['leave_paid_days']);

        $reconciled = $basicPay
            + (float) $i['overtime_pay']
            + (float) $i['holiday_pay']
            + (float) $i['incentive']
            - (float) $i['late_deduction']
            - (float) $i['undertime_deduction']
            - (float) $i['fine_deduction']
            + $leavePay;

        expect(round($reconciled, 2))->toBe(round((float) $i['gross_pay'], 2));
    }
});
