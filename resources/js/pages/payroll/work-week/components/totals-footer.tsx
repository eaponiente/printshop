import { formatCurrency } from '@/utils/formatters';
import type { FooterTotals } from '../types';

type TotalsFooterProps = {
    totals: FooterTotals;
    dayColumnCount: number;
};

export function TotalsFooter({ totals, dayColumnCount }: TotalsFooterProps) {
    return (
        <tfoot>
            <tr className="border-t-2 bg-muted/50 font-semibold">
                <td className="sticky left-0 bg-muted px-3 py-2" colSpan={2 + dayColumnCount}>
                    Totals
                </td>
                <td className="px-2 py-2" />
                <td className="px-2 py-2 text-right font-mono">
                    {totals.total_lates}
                </td>
                <td className="px-2 py-2 text-right font-mono">
                    {totals.total_overtime_hours.toFixed(1)}h
                </td>
                <td className="px-2 py-2 text-center font-mono">
                    {totals.total_holidays}
                </td>
                <td className="px-2 py-2 text-right font-mono text-red-700">
                    {formatCurrency(totals.total_cash_advance)}
                </td>
                <td className="px-2 py-2" colSpan={3} />
                <td className="px-2 py-2 text-right font-mono text-red-700">
                    {formatCurrency(totals.total_deductions)}
                </td>
                <td className="px-2 py-2 text-right font-mono">
                    {formatCurrency(totals.gross_payroll)}
                </td>
                <td className="px-2 py-2 text-right font-mono text-green-700">
                    {formatCurrency(totals.total_net_salary)}
                </td>
            </tr>
        </tfoot>
    );
}
