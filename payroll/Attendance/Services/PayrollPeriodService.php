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

    /**
     * Active, non-superadmin employees in the period's branch that have no
     * payroll item — e.g. hired or reactivated after the period was generated.
     * These block approval: the period would otherwise pay out with someone
     * missing. Mirrors the population generate() computes items for.
     *
     * @return array<int, array{employee_id: int, employee: string}>
     */
    public function findUncomputedEmployees(PayrollPeriod $period): array
    {
        $computedIds = $period->items()->pluck('employee_id')->all();

        return Employee::where('branch_id', $period->branch_id)
            ->where('status', 'active')
            ->whereDoesntHave('user', fn ($q) => $q->where('role', 'superadmin'))
            ->whereNotIn('id', $computedIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn ($e) => [
                'employee_id' => $e->id,
                'employee' => $e->last_name.', '.$e->first_name,
            ])
            ->all();
    }

    /**
     * Items whose net pay was floored to zero because deductions exceeded
     * earnings (the pre-floor net would have been negative). Warning only —
     * this never blocks approval.
     *
     * Detected from stored columns: computeCADeduction skips once the
     * post-statutory pool is non-positive, so a raw net below zero means the
     * statutory deductions alone outran earnings. `net` is that raw negative
     * amount (the stored net_pay is clamped to 0).
     *
     * @return array<int, array{employee_id: int, employee: string, net: float}>
     */
    public function findNegativeNetPay(PayrollPeriod $period): array
    {
        return $period->items()
            ->with('employee:id,first_name,last_name')
            ->get()
            ->map(fn ($item) => [
                'employee_id' => $item->employee_id,
                'employee' => $item->employee
                    ? $item->employee->last_name.', '.$item->employee->first_name
                    : (string) $item->employee_id,
                'net' => round(
                    (float) $item->gross_pay
                    + (float) $item->deminimis_earnings
                    - (float) $item->sss_deduction
                    - (float) $item->philhealth_deduction
                    - (float) $item->pagibig_deduction
                    - (float) $item->ca_deduction,
                    2
                ),
            ])
            ->filter(fn ($row) => $row['net'] < 0)
            ->values()
            ->all();
    }

    public function generate(Branch $branch, string $periodStart, string $periodEnd): PayrollPeriod
    {
        return DB::transaction(function () use ($branch, $periodStart, $periodEnd) {
            $employees = Employee::where('branch_id', $branch->id)
                ->where('status', 'active')
                ->whereDoesntHave('user', fn ($q) => $q->where('role', 'superadmin'))
                ->with(['benefits'])
                ->get();

            $holidayDates = $this->resolveHolidayDates($periodStart, $periodEnd, $branch->id);

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
     * Rebuild a draft period's items in place. Reverses any cash advance
     * deductions the period previously recorded, drops the existing items, then
     * regenerates them from the still-locked attendance sheets plus the
     * *current* cash advances, benefits and statutory settings. Used when
     * something that feeds payroll — most commonly a cash advance granted after
     * the draft was first generated — needs to be reflected. Only draft periods
     * can be recomputed; approved/voided periods are immutable.
     */
    public function recompute(PayrollPeriod $period): void
    {
        if ($period->status !== PayrollPeriodStatus::DRAFT) {
            throw new \RuntimeException('Only draft periods can be recomputed.');
        }

        DB::transaction(function () use ($period) {
            // Serialize concurrent recomputes (double-click / two admins) on the
            // period row. A second call blocks here until the first commits, then
            // re-reads the now-current status — without this lock, overlapping
            // transactions can each reverse the same cash-advance ledger rows and
            // over-restore the balance.
            $period = PayrollPeriod::lockForUpdate()->findOrFail($period->id);

            if ($period->status !== PayrollPeriodStatus::DRAFT) {
                throw new \RuntimeException('Only draft periods can be recomputed.');
            }

            $this->reverseCashAdvanceDeductions($period);
            $period->items()->delete();

            $start = $period->period_start->toDateString();
            $end = $period->period_end->toDateString();

            $employees = Employee::where('branch_id', $period->branch_id)
                ->where('status', 'active')
                ->whereDoesntHave('user', fn ($q) => $q->where('role', 'superadmin'))
                ->with(['benefits'])
                ->get();

            $sheetsByEmployee = AttendanceSheet::whereIn('employee_id', $employees->pluck('id'))
                ->whereBetween('date', [$start, $end])
                ->get()
                ->groupBy('employee_id');

            foreach ($employees as $employee) {
                $this->generateItemForEmployee(
                    $period,
                    $employee,
                    $start,
                    $end,
                    $sheetsByEmployee->get($employee->id, new Collection),
                );
            }
        });
    }

    /**
     * Return all date strings within [start, end] that match a holiday, fixed
     * or recurring, and apply to this branch (nationwide holidays always
     * match; branch-scoped holidays only match their pivoted branches).
     * Batches the holiday lookups (plus their branch pivots) to avoid N+1 in
     * generate() — exactly two queries total regardless of employee/day count.
     */
    protected function resolveHolidayDates(string $periodStart, string $periodEnd, int $branchId): array
    {
        $start = Carbon::parse($periodStart);
        $end = Carbon::parse($periodEnd);

        // The (recurring OR (non-recurring AND between)) pair must stay
        // grouped in its own closure: a later top-level ->where() for the
        // branch predicate would otherwise AND-bind only to the last OR
        // branch (AND binds tighter than OR), leaking every non-recurring
        // holiday across branches. There's no branch predicate here yet
        // (filtering happens in-memory below via appliesToBranch()), but the
        // grouping is kept so adding one later can't silently break this.
        $candidates = Holiday::where(function ($q) use ($periodStart, $periodEnd) {
            $q->where('recurring', true)
                ->orWhere(function ($r) use ($periodStart, $periodEnd) {
                    $r->where('recurring', false)
                        ->whereBetween('date', [$periodStart, $periodEnd]);
                });
        })
            ->with('branches:id')
            ->get();

        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();
            foreach ($candidates as $holiday) {
                $hDate = Carbon::parse($holiday->date);
                $matches = $holiday->recurring
                    ? ($hDate->month === $date->month && $hDate->day === $date->day)
                    : ($hDate->toDateString() === $dateStr);
                if ($matches && $holiday->appliesToBranch($branchId)) {
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
        $incentiveTotal = $sheets->sum('incentive');
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
            'incentive' => $incentiveTotal,
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
            // Lock the period row so a concurrent void/delete/recompute of the
            // same period can't also reverse its cash-advance ledger and
            // over-restore the balance. The re-read sees the committed status.
            $period = PayrollPeriod::lockForUpdate()->findOrFail($period->id);

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

            $this->reverseCashAdvanceDeductions($period);

            $period->update([
                'status' => PayrollPeriodStatus::VOIDED,
            ]);
        });
    }

    /**
     * Statuses a period may be deleted from. Draft periods are removed before
     * approval; approved periods can be deleted to unwind an over-eager
     * approval (both unlock sheets and reverse cash-advance deductions). Paid
     * periods (money already disbursed) and voided periods (already reversed)
     * stay non-deletable.
     */
    protected const DELETABLE_STATUSES = [
        PayrollPeriodStatus::DRAFT,
        PayrollPeriodStatus::APPROVED,
    ];

    public function delete(PayrollPeriod $period): void
    {
        if (! in_array($period->status, self::DELETABLE_STATUSES, true)) {
            throw new \RuntimeException('Only draft or approved periods can be deleted.');
        }

        DB::transaction(function () use ($period) {
            // Lock the period row first. A concurrent double-delete blocks here;
            // once the winner commits and removes the row, the loser's re-read
            // returns null and it exits without reversing the ledger a second
            // time. Also serializes against a racing void/recompute.
            $period = PayrollPeriod::lockForUpdate()->find($period->id);

            if (! $period) {
                return; // already deleted by a concurrent request
            }

            if (! in_array($period->status, self::DELETABLE_STATUSES, true)) {
                throw new \RuntimeException('Only draft or approved periods can be deleted.');
            }

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
        // Lock the employee's cash-advance rows for the duration of the enclosing
        // transaction. Without this, two payroll operations touching the same
        // employee across *different* periods (e.g. a generate racing a recompute,
        // or two overlapping-range generates) can both read the same balance and
        // each deduct it, over-deducting the advance. The period-row lock only
        // serializes same-period work, so the CA row itself must be the guard —
        // matching the lockForUpdate already used when reversing.
        $activeCAs = CashAdvance::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'unpaid'])
            ->where('remaining_balance', '>', 0)
            ->orderBy('created_at')
            ->lockForUpdate()
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
     * period, since voiding/deleting the period undoes its payroll effect.
     * Reverses exactly the amount each advance ledger entry recorded,
     * regardless of whether other (still-existing) periods have since
     * deducted from the same advance.
     *
     * Called by both void() (which keeps the period/items for audit — only
     * the ledger rows are cleared so they don't double-count) and delete()
     * (where the items are removed outright afterward anyway).
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

        CashAdvanceDeduction::whereIn('payroll_period_item_id', $itemIds)->delete();
    }
}
