<?php

use App\Models\Branch;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\CashAdvanceDeduction;
use App\Models\Payroll\CompanyConfig;
use App\Models\Payroll\Employee;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollPeriodItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Payroll\Attendance\Enums\PayrollPeriodStatus;

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
