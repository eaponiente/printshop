<?php

namespace Payroll\Attendance\Services;

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\Employee;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollPeriodItem;
use App\Models\Payroll\SssContributionBracket;
use DB;
use Payroll\Attendance\Enums\PayrollPeriodStatus;

class PayrollPeriodService
{
    private ?array $sssBracketsCache = null;

    public function generate(Branch $branch, string $periodStart, string $periodEnd): PayrollPeriod
    {
        return DB::transaction(function () use ($branch, $periodStart, $periodEnd) {
            AttendanceSheet::whereIn('employee_id', function ($query) use ($branch) {
                $query->select('id')->from('employees')->where('branch_id', $branch->id);
            })
                ->whereBetween('date', [$periodStart, $periodEnd])
                ->whereNull('locked_at')
                ->update(['locked_at' => now()]);

            $period = PayrollPeriod::create([
                'branch_id' => $branch->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => PayrollPeriodStatus::DRAFT,
            ]);

            $employees = Employee::where('branch_id', $branch->id)
                ->where('status', 'active')
                ->get();

            foreach ($employees as $employee) {
                $this->generateItemForEmployee($period, $employee, $periodStart, $periodEnd);
            }

            return $period;
        });
    }

    protected function generateItemForEmployee(
        PayrollPeriod $period,
        Employee $employee,
        string $start,
        string $end,
    ): PayrollPeriodItem {
        $sheets = AttendanceSheet::where('employee_id', $employee->id)
            ->whereBetween('date', [$start, $end])
            ->get();

        $totalRegularDays = $sheets->where('is_present', true)->whereNull('holiday_type')->where('is_rest_day', false)->count();
        $absentDays = $sheets->where('is_present', false)->where('is_rest_day', false)->count();
        $totalLateMinutes = $sheets->sum('late_minutes');
        $lateDeduction = $sheets->sum('late_deduction');
        $totalUndertimeMinutes = $sheets->sum('undertime_minutes');
        $undertimeDeduction = $sheets->sum('undertime_deduction');
        $totalOvertimeMinutes = $sheets->sum('overtime_minutes');
        $overtimePay = $sheets->sum('overtime_pay');
        $holidayPayDays = $sheets->whereNotNull('holiday_pay_percent')->where('holiday_pay_percent', '>', 0)->count();
        $holidayPay = $sheets->sum('holiday_pay');
        $leavePaidDays = $sheets->where('leave_is_paid', true)->count();
        $fineDeduction = $sheets->sum('fine_deduction');
        $dailyWageTotal = $sheets->sum('daily_wage');
        $grossPay = round($dailyWageTotal, 2);

        $deminimisEarnings = $this->computeDeminimis($employee, $period);

        $dailyRate = $employee->current_daily_rate ?? 0;
        $sssDeduction = $this->computeSSS($dailyRate, $employee);
        $philhealthDeduction = $this->computePhilHealth($dailyRate, $employee);
        $pagibigDeduction = $this->computePagIBIG($employee);
        $caDeduction = $this->computeCADeduction($employee, $grossPay + $deminimisEarnings - $sssDeduction - $philhealthDeduction - $pagibigDeduction);

        $netPay = round($grossPay + $deminimisEarnings - $sssDeduction - $philhealthDeduction - $pagibigDeduction - $caDeduction, 2);
        if ($netPay < 0) {
            $netPay = 0;
        }

        return PayrollPeriodItem::create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'total_regular_days' => $totalRegularDays,
            'absent_days' => $absentDays,
            'total_late_minutes' => $totalLateMinutes,
            'late_deduction' => $lateDeduction,
            'total_undertime_minutes' => $totalUndertimeMinutes,
            'undertime_deduction' => $undertimeDeduction,
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'overtime_pay' => $overtimePay,
            'holiday_pay_days' => $holidayPayDays,
            'holiday_pay' => $holidayPay,
            'leave_paid_days' => $leavePaidDays,
            'fine_deduction' => $fineDeduction,
            'gross_pay' => $grossPay,
            'deminimis_earnings' => $deminimisEarnings,
            'sss_deduction' => $sssDeduction,
            'philhealth_deduction' => $philhealthDeduction,
            'pagibig_deduction' => $pagibigDeduction,
            'ca_deduction' => $caDeduction,
            'net_pay' => $netPay,
            'daily_rate' => $dailyRate,
            'sss_bracket' => $this->findSSSBracket($dailyRate * 26),
        ]);
    }

    public function approve(PayrollPeriod $period, int $approvedBy): void
    {
        if ($period->status !== PayrollPeriodStatus::DRAFT) {
            throw new \RuntimeException('Only draft periods can be approved.');
        }

        $period->update([
            'status' => PayrollPeriodStatus::APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);
    }

    public function void(PayrollPeriod $period): void
    {
        DB::transaction(function () use ($period) {
            if ($period->status === PayrollPeriodStatus::VOIDED) {
                throw new \RuntimeException('Period is already voided.');
            }

            AttendanceSheet::whereIn('employee_id', function ($query) use ($period) {
                $query->select('id')->from('employees')->where('branch_id', $period->branch_id);
            })
                ->whereBetween('date', [$period->period_start->toDateString(), $period->period_end->toDateString()])
                ->whereNotNull('locked_at')
                ->update(['locked_at' => null]);

            $period->update([
                'status' => PayrollPeriodStatus::VOIDED,
            ]);
        });
    }

    protected function computeDeminimis(Employee $employee, PayrollPeriod $period): float
    {
        $total = 0;
        $wasPresent = AttendanceSheet::where('employee_id', $employee->id)
            ->whereBetween('date', [$period->period_start->toDateString(), $period->period_end->toDateString()])
            ->where('is_present', true)
            ->exists();

        if (! $wasPresent) {
            return 0;
        }

        $perks = $employee->benefits()
            ->where('type', 'perk')
            ->where('benefit_employee.is_active', true)
            ->where(function ($query) use ($period) {
                $query->whereNull('benefit_employee.end_date')
                    ->orWhere('benefit_employee.end_date', '>', $period->period_end);
            })
            ->where('benefit_employee.effective_date', '<=', $period->period_end)
            ->get();

        foreach ($perks as $perk) {
            $monthlyAmount = $perk->pivot->custom_monthly_amount ?? $perk->monthly_amount ?? 0;
            $total += round($monthlyAmount / 4, 2);
        }

        return $total;
    }

    protected function computeSSS(float $dailyRate, Employee $employee): float
    {
        if (! $employee->sss_number) {
            return 0;
        }

        $monthlySalary = $dailyRate * 26;
        $bracket = SssContributionBracket::findBracket($monthlySalary);

        if (! $bracket) {
            return 0;
        }

        return round($monthlySalary * (float) $bracket->employee_percentage / 100 / 4, 2);
    }

    protected function findSSSBracket(float $monthlySalary): int
    {
        if ($this->sssBracketsCache === null) {
            $this->sssBracketsCache = SssContributionBracket::orderBy('salary_min')->get()->all();
        }

        foreach ($this->sssBracketsCache as $index => $bracket) {
            $max = (float) ($bracket->salary_max ?? PHP_FLOAT_MAX);
            if ($monthlySalary <= $max) {
                return $index + 1;
            }
        }

        return count($this->sssBracketsCache);
    }

    protected function computePhilHealth(float $dailyRate, Employee $employee): float
    {
        if (! $employee->philhealth_number) {
            return 0;
        }

        $monthlySalary = $dailyRate * 26;

        return round($monthlySalary * 0.05 * 0.50 / 4, 2);
    }

    protected function computePagIBIG(Employee $employee): float
    {
        if (! $employee->pagibig_number) {
            return 0;
        }

        return round(100 / 4, 2);
    }

    protected function computeCADeduction(Employee $employee, float $netReceivable): float
    {
        $activeCA = CashAdvance::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'unpaid'])
            ->where('remaining_balance', '>', 0)
            ->first();

        if (! $activeCA) {
            return 0;
        }

        $deduction = min((float) $activeCA->remaining_balance, $netReceivable);
        $newBalance = (float) $activeCA->remaining_balance - $deduction;

        $activeCA->update([
            'remaining_balance' => $newBalance,
            'status' => $newBalance <= 0 ? 'paid' : $activeCA->status,
        ]);

        return round($deduction, 2);
    }
}
