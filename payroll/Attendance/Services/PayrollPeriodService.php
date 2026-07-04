<?php

namespace Payroll\Attendance\Services;

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\CashAdvanceDeduction;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Holiday;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollPeriodItem;
use App\Models\Payroll\TimeLog;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Collection;
use Payroll\Attendance\Enums\PayrollPeriodStatus;

class PayrollPeriodService
{
    public function __construct(private AttendanceService $attendanceService) {}

    public function findIncompleteSheets(Branch $branch, string $periodStart, string $periodEnd): array
    {
        // Fetch present, non-leave, non-rest-day sheets for the period.
        $sheets = AttendanceSheet::whereIn('employee_id', function ($query) use ($branch) {
            $query->select('id')->from('employees')->where('branch_id', $branch->id);
        })
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('is_present', true)
            ->where('is_rest_day', false)
            ->whereNull('leave_type')
            ->with('employee:id,first_name,last_name')
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        if ($sheets->isEmpty()) {
            return [];
        }

        // Batch-fetch all time logs for the period in one query.
        $allLogs = TimeLog::whereIn('employee_id', $sheets->pluck('employee_id')->unique())
            ->whereBetween('timestamp', [
                Carbon::parse($periodStart)->startOfDay(),
                Carbon::parse($periodEnd)->endOfDay(),
            ])
            ->whereNull('duplicate_of')
            ->get()
            ->groupBy(fn ($log) => $log->employee_id.'|'.Carbon::parse($log->timestamp)->toDateString());

        $result = [];

        foreach ($sheets as $sheet) {
            $dateStr = $sheet->date->toDateString();

            $punches = $allLogs->get($sheet->employee_id.'|'.$dateStr) ?? collect();

            $hasIn = $punches->contains(fn ($l) => $l->type->value === 'in');
            $hasOut = $punches->contains(fn ($l) => $l->type->value === 'out');
            $hasLunchOut = $punches->contains(fn ($l) => $l->type->value === 'lunch_out');
            $hasLunchIn = $punches->contains(fn ($l) => $l->type->value === 'lunch_in');
            $hasOvertimeIn = $punches->contains(fn ($l) => $l->type->value === 'overtime_in');
            $hasOvertimeOut = $punches->contains(fn ($l) => $l->type->value === 'overtime_out');

            $reason = null;
            if (! $hasIn) {
                $reason = 'No punch-in recorded';
            } elseif (! $hasOut) {
                $reason = 'Punch-out missing';
            } elseif ($hasLunchOut && ! $hasLunchIn) {
                $reason = 'Lunch return punch missing';
            } elseif (! $hasLunchOut && $hasLunchIn) {
                $reason = 'Lunch break punch missing';
            } elseif ($hasOvertimeIn && ! $hasOvertimeOut) {
                $reason = 'Overtime punch-out missing';
            } elseif (! $hasOvertimeIn && $hasOvertimeOut) {
                $reason = 'Overtime punch-in missing';
            }

            if ($reason !== null) {
                $result[] = [
                    'employee' => $sheet->employee->last_name.', '.$sheet->employee->first_name,
                    'employee_id' => $sheet->employee_id,
                    'date' => $dateStr,
                    'reason' => $reason,
                ];
            }
        }

        return $result;
    }

    public function generate(Branch $branch, string $periodStart, string $periodEnd): PayrollPeriod
    {
        return DB::transaction(function () use ($branch, $periodStart, $periodEnd) {
            $employees = Employee::where('branch_id', $branch->id)
                ->where('status', 'active')
                ->whereDoesntHave('user', fn ($q) => $q->where('role', 'superadmin'))
                ->with(['benefits'])
                ->get();

            $holidayDates = $this->resolveHolidayDates($periodStart, $periodEnd);

            // Ensure holiday sheets exist and are computed before locking
            foreach ($holidayDates as $dateStr) {
                foreach ($employees as $employee) {
                    $this->attendanceService->processDailyAttendance($employee, $dateStr);
                }
            }

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

            $sheetsByEmployee = AttendanceSheet::whereIn('employee_id', $employees->pluck('id'))
                ->whereBetween('date', [$periodStart, $periodEnd])
                ->get()
                ->groupBy('employee_id');

            foreach ($employees as $employee) {
                $this->generateItemForEmployee(
                    $period,
                    $employee,
                    $periodStart,
                    $periodEnd,
                    $sheetsByEmployee->get($employee->id, new Collection),
                );
            }

            return $period;
        });
    }

    /**
     * Return all date strings within [start, end] that match a holiday, fixed
     * or recurring. Batches the holiday lookups to avoid N+1 in generate().
     */
    protected function resolveHolidayDates(string $periodStart, string $periodEnd): array
    {
        $start = Carbon::parse($periodStart);
        $end = Carbon::parse($periodEnd);

        $candidates = Holiday::where('recurring', true)
            ->orWhere(function ($q) use ($periodStart, $periodEnd) {
                $q->where('recurring', false)
                    ->whereBetween('date', [$periodStart, $periodEnd]);
            })
            ->get();

        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();
            foreach ($candidates as $holiday) {
                $hDate = Carbon::parse($holiday->date);
                $matches = $holiday->recurring
                    ? ($hDate->month === $date->month && $hDate->day === $date->day)
                    : ($hDate->toDateString() === $dateStr);
                if ($matches) {
                    $dates[] = $dateStr;
                    break;
                }
            }
        }

        return $dates;
    }

    protected function generateItemForEmployee(
        PayrollPeriod $period,
        Employee $employee,
        string $start,
        string $end,
        Collection $sheets,
    ): PayrollPeriodItem {

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

        $deminimisEarnings = $this->computeDeminimis($employee, $period, $sheets);

        $dailyRate = $employee->current_daily_rate ?? 0;

        // Fixed per-employee weekly deductions, applied as-is each period and
        // gated on the matching government ID. Employer share is always 2x.
        $sssDeduction = $employee->sss_number ? round((float) $employee->sss_deduction_per_week, 2) : 0.0;
        $philhealthDeduction = $employee->philhealth_number ? round((float) $employee->philhealth_deduction_per_week, 2) : 0.0;
        $pagibigDeduction = $employee->pagibig_number ? round((float) $employee->pagibig_deduction_per_week, 2) : 0.0;

        $sssEmployer = round($sssDeduction * 2, 2);
        $philhealthEmployer = round($philhealthDeduction * 2, 2);
        $pagibigEmployer = round($pagibigDeduction * 2, 2);

        $caResult = $this->computeCADeduction($employee, $grossPay + $deminimisEarnings - $sssDeduction - $philhealthDeduction - $pagibigDeduction);
        $caDeduction = $caResult['total'];

        $netPay = round($grossPay + $deminimisEarnings - $sssDeduction - $philhealthDeduction - $pagibigDeduction - $caDeduction, 2);
        if ($netPay < 0) {
            $netPay = 0;
        }

        $item = PayrollPeriodItem::create([
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
            'sss_employer' => $sssEmployer,
            'philhealth_deduction' => $philhealthDeduction,
            'philhealth_employer' => $philhealthEmployer,
            'pagibig_deduction' => $pagibigDeduction,
            'pagibig_employer' => $pagibigEmployer,
            'ca_deduction' => $caDeduction,
            'net_pay' => $netPay,
            'daily_rate' => $dailyRate,
            'sss_bracket' => null,
        ]);

        foreach ($caResult['breakdown'] as $cashAdvanceId => $amount) {
            CashAdvanceDeduction::create([
                'payroll_period_item_id' => $item->id,
                'cash_advance_id' => $cashAdvanceId,
                'amount' => $amount,
            ]);
        }

        return $item;
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
            if ($period->status === PayrollPeriodStatus::DRAFT) {
                throw new \RuntimeException('Draft periods cannot be voided.');
            }

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

    public function delete(PayrollPeriod $period): void
    {
        if ($period->status !== PayrollPeriodStatus::DRAFT) {
            throw new \RuntimeException('Only draft periods can be deleted.');
        }

        DB::transaction(function () use ($period) {
            AttendanceSheet::whereIn('employee_id', function ($query) use ($period) {
                $query->select('id')->from('employees')->where('branch_id', $period->branch_id);
            })
                ->whereBetween('date', [$period->period_start->toDateString(), $period->period_end->toDateString()])
                ->whereNotNull('locked_at')
                ->update(['locked_at' => null]);

            $this->reverseCashAdvanceDeductions($period);

            $period->items()->delete();

            $period->delete();
        });
    }

    protected function computeDeminimis(Employee $employee, PayrollPeriod $period, ?Collection $sheets = null): float
    {
        $total = 0;
        $wasPresent = $sheets !== null
            ? $sheets->contains(fn ($s) => (bool) $s->is_present)
            : AttendanceSheet::where('employee_id', $employee->id)
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

    /**
     * An employee may have several concurrent cash advances. Deduct against
     * each one FIFO (oldest first) until the net receivable for the period is
     * exhausted or every advance is paid off.
     *
     * Returns the total deduction plus a per-advance breakdown (cash_advance_id
     * => amount) so the caller can record a ledger entry per item. That ledger
     * is what lets a period deletion reverse the exact amounts taken from each
     * advance instead of only the lump sum.
     *
     * @return array{total: float, breakdown: array<int, float>}
     */
    protected function computeCADeduction(Employee $employee, float $netReceivable): array
    {
        $activeCAs = CashAdvance::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'unpaid'])
            ->where('remaining_balance', '>', 0)
            ->orderBy('created_at')
            ->get();

        $remaining = $netReceivable;
        $totalDeduction = 0.0;
        $breakdown = [];

        foreach ($activeCAs as $ca) {
            if ($remaining <= 0) {
                break;
            }

            $deduction = min((float) $ca->remaining_balance, $remaining);
            $newBalance = round((float) $ca->remaining_balance - $deduction, 2);

            $ca->update([
                'remaining_balance' => $newBalance,
                'status' => $newBalance <= 0 ? 'paid' : $ca->status,
            ]);

            $breakdown[$ca->id] = round($deduction, 2);
            $totalDeduction += $deduction;
            $remaining -= $deduction;
        }

        return ['total' => round($totalDeduction, 2), 'breakdown' => $breakdown];
    }

    /**
     * Restore cash advance balances that were reduced when generating this
     * period, since deleting the period undoes its payroll effect. Reverses
     * exactly the amount each advance ledger entry recorded, regardless of
     * whether other (still-existing) periods have since deducted from the
     * same advance.
     */
    protected function reverseCashAdvanceDeductions(PayrollPeriod $period): void
    {
        $itemIds = $period->items()->pluck('id');

        if ($itemIds->isEmpty()) {
            return;
        }

        $deductions = CashAdvanceDeduction::whereIn('payroll_period_item_id', $itemIds)->get();

        foreach ($deductions as $deduction) {
            $ca = CashAdvance::lockForUpdate()->find($deduction->cash_advance_id);

            if (! $ca) {
                continue;
            }

            $restoredBalance = round((float) $ca->remaining_balance + (float) $deduction->amount, 2);

            $ca->update([
                'remaining_balance' => $restoredBalance,
                'status' => $restoredBalance > 0 ? 'approved' : $ca->status,
            ]);
        }
    }
}
