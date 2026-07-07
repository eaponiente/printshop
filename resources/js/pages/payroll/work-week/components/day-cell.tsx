import { formatCurrency } from '@/utils/formatters';
import { STATUS_COLORS, STATUS_GLYPHS } from '../status-colors';
import type { DayCellData } from '../types';

type DayCellProps = {
    cell: DayCellData | undefined;
};

// A day is treated as "half day" purely for display when the employee was
// present but worked roughly half a standard shift — there is no dedicated
// half-day column on attendance_sheets, only the resulting hours_worked.
const isHalfDay = (cell: DayCellData) =>
    cell.is_present && cell.hours_worked > 0 && cell.hours_worked <= 4.5;

export function DayCell({ cell }: DayCellProps) {
    if (!cell) {
        return (
            <div
                className={`rounded border border-dashed p-1 text-center text-xs ${STATUS_COLORS.empty}`}
            >
                {STATUS_GLYPHS.empty}
            </div>
        );
    }

    if (cell.is_rest_day) {
        return (
            <div
                className={`rounded border p-1 text-center text-xs font-medium ${STATUS_COLORS.rest}`}
            >
                {STATUS_GLYPHS.rest}
            </div>
        );
    }

    if (cell.leave_type) {
        return (
            <div
                className={`rounded border p-1 text-center text-xs font-medium ${STATUS_COLORS.leave}`}
            >
                {STATUS_GLYPHS.leave}
            </div>
        );
    }

    if (cell.holiday_pay_percent !== null && cell.holiday_pay_percent > 0) {
        return (
            <div
                className={`rounded border p-1 text-center text-xs font-medium ${STATUS_COLORS.holiday}`}
            >
                {STATUS_GLYPHS.holiday}
                <div className="font-mono text-[10px] font-normal">
                    {formatCurrency(cell.daily_wage)}
                </div>
            </div>
        );
    }

    if (!cell.is_present) {
        return (
            <div
                className={`rounded border p-1 text-center text-xs font-medium ${STATUS_COLORS.absent}`}
            >
                {STATUS_GLYPHS.absent}
            </div>
        );
    }

    return (
        <div
            className={`rounded border p-1 text-center text-xs font-medium ${STATUS_COLORS.present}`}
        >
            {isHalfDay(cell) ? 'Half Day' : STATUS_GLYPHS.present}
            <div className="font-mono text-[10px] font-normal">
                {formatCurrency(cell.daily_wage)}
            </div>
            {cell.late_minutes > 0 && (
                <div
                    className={`text-[10px] font-normal ${STATUS_COLORS.late}`}
                >
                    L {cell.late_minutes}m
                </div>
            )}
            {cell.overtime_minutes > 0 && (
                <div className="text-[10px] font-normal text-blue-600">
                    OT {(cell.overtime_minutes / 60).toFixed(1)}h
                </div>
            )}
            {cell.fine_deduction > 0 && (
                <div className="text-[10px] font-normal text-red-600">
                    Fined
                </div>
            )}
        </div>
    );
}
