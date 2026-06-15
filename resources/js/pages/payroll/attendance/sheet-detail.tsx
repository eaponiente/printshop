import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency, formatTime } from '@/utils/formatters';
import SheetFinesCard from './components/SheetFinesCard';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Attendance Sheets', href: '/payroll/attendance-sheets' },
];

type Props = {
    employee: { id: number; full_name: string };
    date: string;
    sheet: {
        is_present: boolean;
        is_rest_day: boolean;
        absence_type: string | null;
        hours_worked: number;
        schedule_start_time: string | null;
        schedule_end_time: string | null;
        daily_rate: number;
        late_minutes: number;
        late_deduction: number;
        undertime_minutes: number;
        undertime_deduction: number;
        overtime_minutes: number;
        overtime_pay: number;
        overtime_multiplier: number | null;
        holiday_type: string | null;
        holiday_pay_percent: number | null;
        holiday_pay: number;
        fine_deduction: number;
        leave_type: string | null;
        leave_duration: string | null;
        leave_is_paid: boolean;
        leave_hours_credited: number;
        daily_wage: number;
    } | null;
    lockedAt: string | null;
    fines: {
        id: number;
        fine_type: string;
        amount: number;
        note: string | null;
    }[];
    timeLogs: {
        id: number;
        type: string;
        timestamp: string;
        latitude: number | null;
        longitude: number | null;
        accuracy_meters: number | null;
        note: string | null;
    }[];
};

export default function SheetDetail({
    employee,
    date,
    sheet,
    lockedAt,
    fines,
    timeLogs,
}: Props) {
    const hourLabel = (h: number) => (h === 8 ? 'Full Day' : `${h}h`);
    const dailyRate = Number(sheet?.daily_rate) || 0;
    const hourlyRate = dailyRate / 8;
    const restDayPremium =
        sheet?.is_rest_day && sheet?.is_present
            ? Math.round(sheet.hours_worked * hourlyRate * 0.3 * 100) / 100
            : 0;

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title={`Sheet — ${employee.full_name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => history.back()}
                    >
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Back
                    </Button>
                    <h1 className="text-xl font-semibold">
                        {employee.full_name} — {date}
                    </h1>
                </div>

                {sheet ? (
                    <div className="max-w-md space-y-4">
                        {/* Status */}
                        <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                            <Row
                                label="Status"
                                value={statusLabel(sheet)}
                                valueClass={statusClass(sheet)}
                            />
                            {sheet.is_present && (
                                <>
                                    <Row
                                        label="Hours Worked"
                                        value={hourLabel(sheet.hours_worked)}
                                    />
                                    {sheet.schedule_start_time && (
                                        <Row
                                            label="Schedule"
                                            value={`${formatTime(sheet.schedule_start_time)} – ${formatTime(sheet.schedule_end_time)}`}
                                        />
                                    )}
                                    <Row
                                        label="Daily Rate"
                                        value={`${formatCurrency(sheet.daily_rate)} (${formatCurrency(Number(sheet.daily_rate) / 8)}/hr)`}
                                    />
                                </>
                            )}
                        </div>

                        {/* Punches */}
                        {timeLogs.length > 0 && (
                            <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                                <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                                    Punches
                                </h3>
                                {timeLogs.map((log) => (
                                    <div
                                        key={log.id}
                                        className="flex justify-between py-0.5"
                                    >
                                        <span className="text-muted-foreground capitalize">
                                            {typeLabel(log.type)}
                                        </span>
                                        <span className="font-mono text-xs">
                                            {new Date(
                                                log.timestamp,
                                            ).toLocaleTimeString('en-PH', {
                                                hour: '2-digit',
                                                minute: '2-digit',
                                                second: '2-digit',
                                            })}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Punch Locations */}
                        {timeLogs.filter(
                            (l) => ['in', 'out'].includes(l.type) && l.note,
                        ).length > 0 && (
                            <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                                <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                                    Punch Locations
                                </h3>
                                {timeLogs
                                    .filter(
                                        (l) =>
                                            ['in', 'out'].includes(l.type) &&
                                            l.note,
                                    )
                                    .map((log) => (
                                        <div
                                            key={log.id}
                                            className="flex justify-between py-0.5"
                                        >
                                            <span className="text-muted-foreground capitalize">
                                                {log.type === 'in'
                                                    ? 'Punch In'
                                                    : 'Punch Out'}
                                            </span>
                                            <span
                                                className={`text-xs ${log.note?.includes('✅') ? 'text-green-600' : log.note?.includes('⚠️') ? 'text-amber-600' : 'text-muted-foreground'}`}
                                            >
                                                {log.note}
                                            </span>
                                        </div>
                                    ))}
                            </div>
                        )}

                        {/* Deductions */}
                        {(sheet.late_deduction > 0 ||
                            sheet.undertime_deduction > 0) && (
                            <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                                <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                                    Deductions
                                </h3>
                                {sheet.late_deduction > 0 && (
                                    <Row
                                        label="Late"
                                        value={`${sheet.late_minutes} min → −${formatCurrency(sheet.late_deduction)}`}
                                        red
                                    />
                                )}
                                {sheet.undertime_deduction > 0 && (
                                    <Row
                                        label="Undertime"
                                        value={`${sheet.undertime_minutes} min → −${formatCurrency(sheet.undertime_deduction)}`}
                                        red
                                    />
                                )}
                            </div>
                        )}

                        {/* Additions */}
                        {(sheet.overtime_pay > 0 ||
                            sheet.holiday_pay > 0 ||
                            restDayPremium > 0) && (
                            <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                                <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                                    Additions
                                </h3>
                                {restDayPremium > 0 && (
                                    <Row
                                        label="Rest Day"
                                        value={`${sheet.hours_worked}h × 1.30× → +${formatCurrency(restDayPremium)}`}
                                    />
                                )}
                                {sheet.overtime_pay > 0 && (
                                    <Row
                                        label="Overtime"
                                        value={`${sheet.overtime_minutes} min × ${sheet.overtime_multiplier ?? '—'}× → +${formatCurrency(sheet.overtime_pay)}`}
                                    />
                                )}
                                {sheet.holiday_pay > 0 && (
                                    <Row
                                        label="Holiday"
                                        value={`${sheet.holiday_type} ${sheet.holiday_pay_percent}% → +${formatCurrency(sheet.holiday_pay)}`}
                                    />
                                )}
                            </div>
                        )}

                        {/* Leave */}
                        {sheet.leave_type && (
                            <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                                <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                                    Leave
                                </h3>
                                <Row label="Type" value={sheet.leave_type} />
                                <Row
                                    label="Duration"
                                    value={
                                        sheet.leave_duration?.replace(
                                            /_/g,
                                            ' ',
                                        ) ?? '—'
                                    }
                                />
                                <Row
                                    label="Credited"
                                    value={`${sheet.leave_hours_credited}h`}
                                />
                                <Row
                                    label="Paid"
                                    value={sheet.leave_is_paid ? 'Yes' : 'No'}
                                />
                            </div>
                        )}

                        {/* Fines */}
                        <SheetFinesCard
                            employeeId={employee.id}
                            date={date}
                            fines={fines}
                            lockedAt={lockedAt}
                        />

                        {/* Daily Wage */}
                        <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                            <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                                Daily Wage
                            </h3>
                            <Row
                                label="Total"
                                value={formatCurrency(sheet.daily_wage)}
                                bold
                            />
                        </div>
                    </div>
                ) : (
                    <p className="text-muted-foreground">
                        No attendance sheet for this date.
                    </p>
                )}
            </div>
        </PayrollLayout>
    );
}

function Row({
    label,
    value,
    valueClass,
    red,
    bold,
}: {
    label: string;
    value: string;
    valueClass?: string;
    red?: boolean;
    bold?: boolean;
}) {
    return (
        <div className={`flex justify-between ${bold ? 'font-semibold' : ''}`}>
            <span className="text-muted-foreground">{label}</span>
            <span className={valueClass ?? (red ? 'text-red-600' : '')}>
                {value}
            </span>
        </div>
    );
}

function statusLabel(sheet: NonNullable<Props['sheet']>) {
    if (sheet.is_rest_day) {
        return 'Rest Day';
    }

    if (sheet.is_present) {
        return 'Present';
    }

    if (sheet.absence_type === 'unexcused') {
        return 'Absent (Unexcused)';
    }

    return sheet.absence_type ?? 'Absent';
}

function statusClass(sheet: NonNullable<Props['sheet']>) {
    if (sheet.is_rest_day) {
        return 'font-medium text-blue-500';
    }

    if (sheet.is_present) {
        return 'font-medium text-green-600';
    }

    return 'font-medium text-red-500';
}

function typeLabel(type: string) {
    const map: Record<string, string> = {
        in: 'Punch In',
        lunch_out: 'Lunch Out',
        lunch_in: 'Lunch In',
        out: 'Punch Out',
    };

    return map[type] ?? type;
}
