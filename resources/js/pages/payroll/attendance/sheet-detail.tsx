import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Lock, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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
        incentive: number;
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
    canEdit: boolean;
};

const PUNCH_TYPES = [
    { value: 'in', label: 'Punch In' },
    { value: 'lunch_out', label: 'Start Break' },
    { value: 'lunch_in', label: 'End Break' },
    { value: 'out', label: 'Punch Out' },
    { value: 'overtime_in', label: 'OT In' },
    { value: 'overtime_out', label: 'OT Out' },
];

export default function SheetDetail({
    employee,
    date,
    sheet,
    lockedAt,
    fines,
    timeLogs,
    canEdit,
}: Props) {
    const [addType, setAddType] = useState('in');
    const [addTime, setAddTime] = useState('');
    const [adding, setAdding] = useState(false);
    const [incentiveValue, setIncentiveValue] = useState(
        String(sheet?.incentive ?? 0),
    );
    const [savingIncentive, setSavingIncentive] = useState(false);

    const hourLabel = (h: number) => (h === 8 ? 'Full Day' : `${h}h`);

    function handleSaveIncentive() {
        const parsed = Number(incentiveValue);
        if (incentiveValue.trim() === '' || Number.isNaN(parsed) || parsed < 0) {
            toast.error('Enter a valid incentive amount.');
            return;
        }

        setSavingIncentive(true);
        router.patch(
            `/payroll/attendance-sheets/${employee.id}/incentive`,
            { date, incentive: parsed },
            {
                onSuccess: () => toast.success('Incentive updated.'),
                onError: () => toast.error('Failed to update incentive.'),
                onFinish: () => setSavingIncentive(false),
            },
        );
    }

    function handleAddPunch() {
        if (!addTime) {
            return;
        }

        setAdding(true);
        router.post(
            `/payroll/attendance-sheets/${employee.id}/logs`,
            { date, type: addType, time: addTime },
            {
                onSuccess: () => {
                    setAddTime('');
                    toast.success('Punch added.');
                },
                onError: () => toast.error('Failed to add punch.'),
                onFinish: () => setAdding(false),
            },
        );
    }

    function handleDeletePunch(logId: number) {
        router.delete(
            `/payroll/attendance-sheets/${employee.id}/logs/${logId}`,
            {
                onSuccess: () => toast.success('Punch removed.'),
                onError: () => toast.error('Failed to remove punch.'),
            },
        );
    }

    const showPunchesCard = timeLogs.length > 0 || canEdit;

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
                    {lockedAt && (
                        <span className="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-700">
                            Locked in payroll period
                        </span>
                    )}
                </div>

                <div className="max-w-md space-y-4">
                    {/* Punches — always visible to admin */}
                    {showPunchesCard && (
                        <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                            <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                                Punches
                            </h3>

                            {timeLogs.length === 0 && (
                                <p className="mb-2 text-xs text-muted-foreground">
                                    No punches recorded.
                                </p>
                            )}

                            {timeLogs.map((log) => (
                                <div
                                    key={log.id}
                                    className="flex items-center justify-between py-0.5"
                                >
                                    <span className="text-muted-foreground capitalize">
                                        {typeLabel(log.type)}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <span className="font-mono text-xs">
                                            {new Date(
                                                log.timestamp,
                                            ).toLocaleTimeString('en-PH', {
                                                hour: '2-digit',
                                                minute: '2-digit',
                                                second: '2-digit',
                                            })}
                                        </span>
                                        {canEdit && (
                                            <button
                                                onClick={() =>
                                                    handleDeletePunch(log.id)
                                                }
                                                className="text-muted-foreground hover:text-red-500"
                                                title="Remove punch"
                                            >
                                                <X className="h-3 w-3" />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}

                            {canEdit && (
                                <div className="mt-3 flex items-center gap-2 border-t border-sidebar-border pt-3">
                                    <select
                                        value={addType}
                                        onChange={(e) =>
                                            setAddType(e.target.value)
                                        }
                                        className="h-7 rounded border border-input bg-background px-2 text-xs"
                                    >
                                        {PUNCH_TYPES.map((t) => (
                                            <option
                                                key={t.value}
                                                value={t.value}
                                            >
                                                {t.label}
                                            </option>
                                        ))}
                                    </select>
                                    <input
                                        type="time"
                                        value={addTime}
                                        onChange={(e) =>
                                            setAddTime(e.target.value)
                                        }
                                        className="h-7 rounded border border-input bg-background px-2 font-mono text-xs"
                                    />
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="h-7 text-xs"
                                        disabled={!addTime || adding}
                                        onClick={handleAddPunch}
                                    >
                                        <Plus className="mr-1 h-3 w-3" /> Add
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}

                    {sheet ? (
                        <>
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
                                            value={hourLabel(
                                                sheet.hours_worked,
                                            )}
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
                                                ['in', 'out'].includes(
                                                    l.type,
                                                ) && l.note,
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
                                sheet.holiday_pay > 0) && (
                                <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                                    <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                                        Additions
                                    </h3>
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
                                    <Row
                                        label="Type"
                                        value={sheet.leave_type}
                                    />
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
                                        value={
                                            sheet.leave_is_paid ? 'Yes' : 'No'
                                        }
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

                            {/* Incentive */}
                            <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                                <div className="mb-2 flex items-center gap-2">
                                    <h3 className="text-xs font-semibold text-muted-foreground uppercase">
                                        Incentive
                                    </h3>
                                    {lockedAt && (
                                        <span className="inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">
                                            <Lock className="h-3 w-3" />
                                            Locked
                                        </span>
                                    )}
                                </div>
                                <Row
                                    label="Current"
                                    value={formatCurrency(sheet.incentive)}
                                />
                                {canEdit && !lockedAt && (
                                    <div className="mt-3 flex items-center gap-2 border-t border-sidebar-border pt-3">
                                        <input
                                            type="text"
                                            inputMode="decimal"
                                            value={incentiveValue}
                                            onChange={(e) =>
                                                setIncentiveValue(
                                                    e.target.value.replace(
                                                        /[^0-9.]/g,
                                                        '',
                                                    ),
                                                )
                                            }
                                            className="h-7 w-28 rounded border border-input bg-background px-2 text-xs"
                                        />
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="h-7 text-xs"
                                            disabled={savingIncentive}
                                            onClick={handleSaveIncentive}
                                        >
                                            Save
                                        </Button>
                                    </div>
                                )}
                            </div>

                            {/* Daily Wage */}
                            <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                                <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                                    Daily Wage
                                </h3>
                                {sheet.incentive > 0 && (
                                    <Row
                                        label="Incentive"
                                        value={formatCurrency(
                                            sheet.incentive,
                                        )}
                                    />
                                )}
                                <Row
                                    label="Total"
                                    value={formatCurrency(sheet.daily_wage)}
                                    bold
                                />
                            </div>
                        </>
                    ) : (
                        !showPunchesCard && (
                            <p className="text-muted-foreground">
                                No attendance sheet for this date.
                            </p>
                        )
                    )}
                </div>
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
        lunch_out: 'Start Break',
        lunch_in: 'End Break',
        out: 'Punch Out',
        overtime_in: 'OT In',
        overtime_out: 'OT Out',
    };

    return map[type] ?? type;
}
