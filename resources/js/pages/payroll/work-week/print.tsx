import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/utils/formatters';
import { STATUS_GLYPHS } from './status-colors';
import type {
    DayCellData,
    EmployeeListItem,
    FooterTotals,
    EmployeeRowTotals,
} from './types';

const dayLabels = ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

type Props = {
    branch: { id: number; name: string };
    startDate: string;
    endDate: string;
    dayColumns: string[];
    employees: EmployeeListItem[];
    cells: Record<string, DayCellData>;
    rowTotals: Record<number, EmployeeRowTotals>;
    footerTotals: FooterTotals;
};

const cellGlyph = (cell: DayCellData | undefined): string => {
    if (!cell) {
        return STATUS_GLYPHS.empty;
    }

    // Only an unworked rest day shows "Rest"; a worked rest day is paid like a
    // regular day, so it falls through to the present/late glyph below.
    if (cell.is_rest_day && !cell.is_present) {
        return STATUS_GLYPHS.rest;
    }

    if (cell.leave_type) {
        return STATUS_GLYPHS.leave;
    }

    if (cell.holiday_pay_percent !== null && cell.holiday_pay_percent > 0) {
        return STATUS_GLYPHS.holiday;
    }

    if (!cell.is_present) {
        return STATUS_GLYPHS.absent;
    }

    return cell.late_minutes > 0
        ? `${STATUS_GLYPHS.present} L${cell.late_minutes}m`
        : STATUS_GLYPHS.present;
};

export default function WorkWeekPrint({
    branch,
    startDate,
    endDate,
    dayColumns,
    employees,
    cells,
    rowTotals,
    footerTotals,
}: Props) {
    return (
        <>
            <Head title="Print Payroll — Work Week Table" />

            <style>{`
                @media print {
                    @page { margin: 6mm; size: A4 landscape; }
                    table { break-inside: auto; }
                    tr { break-inside: avoid; }
                    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; font-size: 8px; }
                    .no-print { display: none !important; }
                }
                @media screen {
                    body { background: #f5f5f5; padding: 0; }
                }
            `}</style>

            <div className="no-print sticky top-0 z-10 flex items-center justify-between border-b bg-white px-4 py-3 shadow-sm">
                <div>
                    <h1 className="text-sm font-semibold">
                        Work Week Payroll Table — {branch.name}
                    </h1>
                    <p className="text-xs text-muted-foreground">
                        {startDate} to {endDate} · Live preview, not a
                        generated payroll period
                    </p>
                </div>
                <Button size="sm" onClick={() => window.print()}>
                    <Printer className="mr-2 h-4 w-4" />
                    Print
                </Button>
            </div>

            <div className="mx-auto max-w-full overflow-x-auto bg-white p-4">
                <table className="w-full border-collapse text-[9px]">
                    <thead>
                        <tr className="border-b-2 border-black">
                            <th className="p-1 text-left">Employee</th>
                            <th className="p-1 text-right">Rate</th>
                            {dayColumns.map((date, i) => (
                                <th key={date} className="p-1 text-center">
                                    {dayLabels[i]}
                                </th>
                            ))}
                            <th className="p-1 text-right">Fines</th>
                            <th className="p-1 text-right">Late(m)</th>
                            <th className="p-1 text-right">OT(h)</th>
                            <th className="p-1 text-center">Hol.</th>
                            <th className="p-1 text-right">CA</th>
                            <th className="p-1 text-right">SSS</th>
                            <th className="p-1 text-right">PHIC</th>
                            <th className="p-1 text-right">PAG</th>
                            <th className="p-1 text-right">Total Ded.</th>
                            <th className="p-1 text-right">Gross</th>
                            <th className="p-1 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        {employees.map((emp) => {
                            const totals = rowTotals[emp.id];

                            if (!totals) {
                                return null;
                            }

                            return (
                                <tr key={emp.id} className="border-b">
                                    <td className="p-1">{emp.full_name}</td>
                                    <td className="p-1 text-right font-mono">
                                        {formatCurrency(totals.daily_rate)}
                                    </td>
                                    {dayColumns.map((date) => (
                                        <td
                                            key={date}
                                            className="p-1 text-center"
                                        >
                                            {cellGlyph(
                                                cells[`${emp.id}-${date}`],
                                            )}
                                        </td>
                                    ))}
                                    <td className="p-1 text-right font-mono">
                                        {formatCurrency(totals.fine_deduction)}
                                    </td>
                                    <td className="p-1 text-right font-mono">
                                        {totals.late_minutes}
                                    </td>
                                    <td className="p-1 text-right font-mono">
                                        {totals.overtime_hours.toFixed(1)}
                                    </td>
                                    <td className="p-1 text-center font-mono">
                                        {totals.holiday_count}
                                    </td>
                                    <td className="p-1 text-right font-mono">
                                        {formatCurrency(totals.cash_advance)}
                                    </td>
                                    <td className="p-1 text-right font-mono">
                                        {formatCurrency(totals.sss_deduction)}
                                    </td>
                                    <td className="p-1 text-right font-mono">
                                        {formatCurrency(
                                            totals.philhealth_deduction,
                                        )}
                                    </td>
                                    <td className="p-1 text-right font-mono">
                                        {formatCurrency(
                                            totals.pagibig_deduction,
                                        )}
                                    </td>
                                    <td className="p-1 text-right font-mono font-semibold">
                                        {formatCurrency(
                                            totals.total_deductions,
                                        )}
                                    </td>
                                    <td className="p-1 text-right font-mono font-semibold">
                                        {formatCurrency(totals.gross_pay)}
                                    </td>
                                    <td className="p-1 text-right font-mono font-semibold">
                                        {formatCurrency(totals.net_pay)}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                    <tfoot>
                        <tr className="border-t-2 border-black font-semibold">
                            <td
                                className="p-1"
                                colSpan={2 + dayColumns.length + 4}
                            >
                                Totals
                            </td>
                            <td className="p-1 text-right font-mono">
                                {formatCurrency(footerTotals.total_cash_advance)}
                            </td>
                            <td className="p-1" colSpan={3} />
                            <td className="p-1 text-right font-mono">
                                {formatCurrency(footerTotals.total_deductions)}
                            </td>
                            <td className="p-1 text-right font-mono">
                                {formatCurrency(footerTotals.gross_payroll)}
                            </td>
                            <td className="p-1 text-right font-mono">
                                {formatCurrency(footerTotals.total_net_salary)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </>
    );
}
