<?php

namespace Payroll\Attendance\Services;

use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\Employee;
use Illuminate\Support\Collection;

/**
 * Read-only, live preview of payroll totals for an arbitrary date range.
 * Unlike PayrollPeriodService, this never creates a PayrollPeriod, never
 * locks attendance sheets, and never mutates a CashAdvance balance — the
 * cash advance figure is a dry-run FIFO preview only.
 */
class WorkWeekTableService
{
    public function buildPage(Branch $branch, string $startDate, string $endDate, int $perPage = 25): array
    {
        $employees = $this->employeeQuery($branch)->simplePaginate($perPage);

        [$cells, $rowTotals] = $this->buildCellsAndTotals($employees->getCollection(), $startDate, $endDate);

        return [
            'employees' => $employees,
            'cells' => $cells,
            'rowTotals' => $rowTotals,
        ];
    }

    public function buildFull(Branch $branch, string $startDate, string $endDate): array
    {
        $employees = $this->employeeQuery($branch)->get();

        [$cells, $rowTotals] = $this->buildCellsAndTotals($employees, $startDate, $endDate);

        return [
            'employees' => $employees,
            'cells' => $cells,
            'rowTotals' => $rowTotals,
            'footerTotals' => $this->aggregateFooterTotals($rowTotals),
        ];
    }

    protected function employeeQuery(Branch $branch)
    {
        return Employee::where('branch_id', $branch->id)
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /**
     * @return array{0: array<string, array>, 1: array<int, array>}
     */
    protected function buildCellsAndTotals(Collection $employees, string $startDate, string $endDate): array
    {
        $employeeIds = $employees->pluck('id');

        $sheetsByEmployee = AttendanceSheet::whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        $cashAdvancesByEmployee = CashAdvance::whereIn('employee_id', $employeeIds)
            ->whereIn('status', ['approved', 'unpaid'])
            ->where('remaining_balance', '>', 0)
            ->orderBy('created_at')
            ->get()
            ->groupBy('employee_id');

        $cells = [];
        $rowTotals = [];

        foreach ($employees as $employee) {
            $sheets = $sheetsByEmployee->get($employee->id, collect());

            foreach ($sheets as $sheet) {
                $cells["{$employee->id}-{$sheet->date->toDateString()}"] = $this->cellPayload($sheet);
            }

            $rowTotals[$employee->id] = $this->buildRowTotals(
                $employee,
                $sheets,
                $cashAdvancesByEmployee->get($employee->id, collect()),
            );
        }

        return [$cells, $rowTotals];
    }

    protected function cellPayload(AttendanceSheet $sheet): array
    {
        return [
            'is_present' => (bool) $sheet->is_present,
            'is_rest_day' => (bool) $sheet->is_rest_day,
            'absence_type' => $sheet->absence_type,
            'holiday_type' => $sheet->holiday_type,
            'holiday_pay_percent' => $sheet->holiday_pay_percent !== null ? (float) $sheet->holiday_pay_percent : null,
            'leave_type' => $sheet->leave_type,
            'leave_duration' => $sheet->leave_duration,
            'late_minutes' => (int) $sheet->late_minutes,
            'overtime_minutes' => (int) $sheet->overtime_minutes,
            'hours_worked' => (float) $sheet->hours_worked,
            'daily_wage' => (float) $sheet->daily_wage,
            'fine_deduction' => (float) $sheet->fine_deduction,
        ];
    }

    protected function buildRowTotals(Employee $employee, Collection $sheets, Collection $cashAdvances): array
    {
        $lateMinutes = (int) $sheets->sum('late_minutes');
        $overtimeMinutes = (int) $sheets->sum('overtime_minutes');
        $fineDeduction = round((float) $sheets->sum('fine_deduction'), 2);
        $holidayCount = $sheets->filter(fn ($s) => $s->holiday_pay_percent !== null && $s->holiday_pay_percent > 0)->count();

        // daily_wage already bakes in late/undertime/fine deductions and OT/holiday
        // pay — never re-subtract any of those on top of it (see CLAUDE.md).
        $grossPay = round((float) $sheets->sum('daily_wage'), 2);

        $sssDeduction = $employee->sss_number ? round((float) $employee->sss_deduction_per_week, 2) : 0.0;
        $philhealthDeduction = $employee->philhealth_number ? round((float) $employee->philhealth_deduction_per_week, 2) : 0.0;
        $pagibigDeduction = $employee->pagibig_number ? round((float) $employee->pagibig_deduction_per_week, 2) : 0.0;

        $netReceivable = $grossPay - $sssDeduction - $philhealthDeduction - $pagibigDeduction;
        $caDeduction = $this->previewCashAdvanceDeduction($cashAdvances, $netReceivable);

        $totalDeductions = round($sssDeduction + $philhealthDeduction + $pagibigDeduction + $caDeduction, 2);

        $netPay = round($grossPay - $sssDeduction - $philhealthDeduction - $pagibigDeduction - $caDeduction, 2);
        if ($netPay < 0) {
            $netPay = 0.0;
        }

        return [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
            'daily_rate' => (float) ($employee->current_daily_rate ?? 0),
            'fine_deduction' => $fineDeduction,
            'late_minutes' => $lateMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_hours' => round($overtimeMinutes / 60, 2),
            'holiday_count' => $holidayCount,
            'cash_advance' => $caDeduction,
            'sss_deduction' => $sssDeduction,
            'philhealth_deduction' => $philhealthDeduction,
            'pagibig_deduction' => $pagibigDeduction,
            'total_deductions' => $totalDeductions,
            'gross_pay' => $grossPay,
            'net_pay' => $netPay,
        ];
    }

    /**
     * Read-only twin of PayrollPeriodService::computeCADeduction() — same FIFO
     * ordering/capping logic, but never calls update()/save() on a CashAdvance.
     */
    protected function previewCashAdvanceDeduction(Collection $cashAdvances, float $netReceivable): float
    {
        $remaining = $netReceivable;
        $totalDeduction = 0.0;

        foreach ($cashAdvances as $ca) {
            if ($remaining <= 0) {
                break;
            }

            $deduction = min((float) $ca->remaining_balance, $remaining);
            $totalDeduction += $deduction;
            $remaining -= $deduction;
        }

        return round($totalDeduction, 2);
    }

    protected function aggregateFooterTotals(array $rowTotals): array
    {
        $rows = collect($rowTotals);

        return [
            'gross_payroll' => round($rows->sum('gross_pay'), 2),
            'total_deductions' => round($rows->sum('total_deductions'), 2),
            'total_net_salary' => round($rows->sum('net_pay'), 2),
            'total_cash_advance' => round($rows->sum('cash_advance'), 2),
            'total_overtime_hours' => round($rows->sum('overtime_hours'), 2),
            'total_holidays' => $rows->sum('holiday_count'),
            'total_lates' => $rows->sum('late_minutes'),
        ];
    }
}
