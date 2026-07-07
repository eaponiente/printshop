import { formatCurrency } from '@/utils/formatters';
import type { DayCellData, EmployeeRowTotals } from '../types';
import { DayCell } from './day-cell';

type WorkWeekRowProps = {
    totals: EmployeeRowTotals;
    dayColumns: string[];
    cells: Record<string, DayCellData>;
};

export function WorkWeekRow({ totals, dayColumns, cells }: WorkWeekRowProps) {
    return (
        <tr className="border-b">
            <td className="sticky left-0 bg-sidebar px-3 py-2 font-medium whitespace-nowrap">
                {totals.full_name}
            </td>
            <td className="px-2 py-2 text-right font-mono whitespace-nowrap">
                {formatCurrency(totals.daily_rate)}
            </td>
            {dayColumns.map((date) => (
                <td key={date} className="w-24 px-1 py-1">
                    <DayCell cell={cells[`${totals.employee_id}-${date}`]} />
                </td>
            ))}
            <td className="px-2 py-2 text-right font-mono text-red-600">
                {formatCurrency(totals.fine_deduction)}
            </td>
            <td className="px-2 py-2 text-right font-mono">
                {totals.late_minutes}
            </td>
            <td className="px-2 py-2 text-right font-mono">
                {totals.overtime_hours.toFixed(1)}h
            </td>
            <td className="px-2 py-2 text-center font-mono">
                {totals.holiday_count}
            </td>
            <td className="px-2 py-2 text-right font-mono text-red-600">
                {formatCurrency(totals.cash_advance)}
            </td>
            <td className="px-2 py-2 text-right font-mono">
                {formatCurrency(totals.sss_deduction)}
            </td>
            <td className="px-2 py-2 text-right font-mono">
                {formatCurrency(totals.philhealth_deduction)}
            </td>
            <td className="px-2 py-2 text-right font-mono">
                {formatCurrency(totals.pagibig_deduction)}
            </td>
            <td className="px-2 py-2 text-right font-mono font-semibold text-red-700">
                {formatCurrency(totals.total_deductions)}
            </td>
            <td className="px-2 py-2 text-right font-mono font-semibold">
                {formatCurrency(totals.gross_pay)}
            </td>
            <td className="px-2 py-2 text-right font-mono font-semibold text-green-700">
                {formatCurrency(totals.net_pay)}
            </td>
        </tr>
    );
}
