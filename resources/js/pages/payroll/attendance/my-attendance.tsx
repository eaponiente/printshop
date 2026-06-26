import { Head, router, useForm } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import {
    CheckCircle2,
    Clock,
    Coffee,
    LogIn,
    LogOut,
    MapPin,
    MinusCircle,
    PlusCircle,
    UserCog,
    UtensilsCrossed,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { DataTable } from '@/components/data-table';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { toDateInput } from '@/utils/dateHelper';
import { formatCurrency, formatTime } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'My Attendance', href: '/payroll/attendance' },
];

type TimeLogRow = {
    id: number;
    type: string;
    timestamp: string;
    source: string;
};

type AttendanceSheetRow = {
    id: number;
    date: string;
    is_present: boolean;
    is_rest_day: boolean;
    hours_worked: number;
    late_minutes: number;
    undertime_minutes: number;
    overtime_minutes: number;
    holiday_pay_percent: number | null;
    daily_wage: number;
    locked_at: string | null;
};

type Props = {
    punchState: any | null;
    employee: {
        id: number;
        employee_number: string;
        first_name: string;
        last_name: string;
        middle_name: string | null;
        full_name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
        birth_date: string | null;
        hire_date: string;
        end_date: string | null;
        position: string;
        status: string;
        current_daily_rate: number;
        sss_number: string | null;
        philhealth_number: string | null;
        pagibig_number: string | null;
        tin_number: string | null;
        notes: string | null;
        branch: { name: string } | null;
    } | null;
    activeSchedule: {
        start_time: string;
        end_time: string;
        rest_days: number[];
        effective_from: string;
        effective_to: string | null;
    } | null;
    enableCustomPunchTime: boolean;
    attendanceSheets: PaginatedResponse<AttendanceSheetRow> | null;
    recentTimeLogs: TimeLogRow[];
};

export default function MyAttendance(props: Props) {
    const { punchState, employee } = props;
    const [profileOpen, setProfileOpen] = useState(false);

    if (!employee) {
        return (
            <PayrollLayout breadcrumbs={breadcrumbs}>
                <Head title="My Attendance" />
                <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-4">
                    <p className="text-lg text-muted-foreground">
                        No employee record linked to your account.
                    </p>
                </div>
            </PayrollLayout>
        );
    }

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="My Attendance" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">My Attendance</h1>
                        <p className="text-sm text-muted-foreground">
                            {employee.full_name}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setProfileOpen(true)}
                        className="gap-1.5"
                    >
                        <UserCog className="h-4 w-4" />
                        Profile
                    </Button>
                </div>

                <PunchTab
                    punchState={punchState}
                    attendanceSheets={props.attendanceSheets}
                    recentTimeLogs={props.recentTimeLogs}
                    enableCustomPunchTime={props.enableCustomPunchTime}
                />

                <Sheet open={profileOpen} onOpenChange={setProfileOpen}>
                    <SheetContent
                        side="right"
                        className="w-full overflow-y-auto sm:max-w-xl"
                    >
                        <SheetHeader>
                            <SheetTitle>Profile</SheetTitle>
                            <SheetDescription>
                                Update your personal details and statutory IDs.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="px-4 pb-6">
                            <ProfileTab
                                employee={employee}
                                activeSchedule={props.activeSchedule}
                            />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </PayrollLayout>
    );
}

const attendanceColumns: ColumnDef<AttendanceSheetRow, any>[] = [
    {
        accessorKey: 'date',
        header: 'Date',
        cell: ({ row }: CellContext<AttendanceSheetRow, any>) => (
            <span className="font-mono text-xs whitespace-nowrap">
                {new Date(row.original.date).toLocaleDateString('en-PH', {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric',
                })}
            </span>
        ),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }: CellContext<AttendanceSheetRow, any>) => {
            if (row.original.is_present) {
                return (
                    <span className="text-xs font-medium text-green-600">
                        Present
                    </span>
                );
            }

            if (row.original.is_rest_day) {
                return <span className="text-xs text-blue-500">Rest</span>;
            }

            return <span className="text-xs text-red-500">Absent</span>;
        },
    },
    {
        accessorKey: 'hours_worked',
        header: 'Hours',
        cell: ({ row }: CellContext<AttendanceSheetRow, any>) => (
            <span className="font-mono text-xs">
                {row.original.hours_worked}
            </span>
        ),
    },
    {
        accessorKey: 'late_minutes',
        header: 'Late',
        cell: ({ row }: CellContext<AttendanceSheetRow, any>) => (
            <span className="text-xs">
                {row.original.late_minutes > 0
                    ? `${row.original.late_minutes}m`
                    : '—'}
            </span>
        ),
    },
    {
        accessorKey: 'undertime_minutes',
        header: 'UT',
        cell: ({ row }: CellContext<AttendanceSheetRow, any>) => (
            <span className="text-xs">
                {row.original.undertime_minutes > 0
                    ? `${row.original.undertime_minutes}m`
                    : '—'}
            </span>
        ),
    },
    {
        accessorKey: 'overtime_minutes',
        header: 'OT',
        cell: ({ row }: CellContext<AttendanceSheetRow, any>) => (
            <span className="text-xs">
                {row.original.overtime_minutes > 0
                    ? `${row.original.overtime_minutes}m`
                    : '—'}
            </span>
        ),
    },
    {
        accessorKey: 'holiday_pay_percent',
        header: 'Holiday',
        cell: ({ row }: CellContext<AttendanceSheetRow, any>) => (
            <span className="text-xs">
                {row.original.holiday_pay_percent
                    ? `${row.original.holiday_pay_percent}%`
                    : '—'}
            </span>
        ),
    },
    {
        accessorKey: 'daily_wage',
        header: 'Wage',
        cell: ({ row }: CellContext<AttendanceSheetRow, any>) => (
            <span className="font-mono text-xs font-medium whitespace-nowrap">
                {formatCurrency(row.original.daily_wage)}
            </span>
        ),
    },
];

function PunchTab({
    punchState,
    attendanceSheets,
    recentTimeLogs,
    enableCustomPunchTime,
}: {
    punchState: any;
    attendanceSheets: PaginatedResponse<AttendanceSheetRow> | null;
    recentTimeLogs: TimeLogRow[];
    enableCustomPunchTime: boolean;
}) {
    const today = new Date().toISOString().substring(0, 10);
    const now = new Date();
    const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

    const [selectedDate, setSelectedDate] = useState(today);
    const [selectedTime, setSelectedTime] = useState(currentTime);
    const [confirmPunchType, setConfirmPunchType] = useState<string | null>(
        null,
    );

    const firstInLog = punchState?.logs?.find(
        (l: any) =>
            (typeof l.type === 'string' ? l.type : l.type?.value) === 'in',
    );
    const lastOutLog = [...(punchState?.logs || [])]
        .reverse()
        .find(
            (l: any) =>
                (typeof l.type === 'string' ? l.type : l.type?.value) === 'out',
        );

    const displayTime = enableCustomPunchTime
        ? `${selectedDate} ${selectedTime}`
        : new Date().toLocaleString('en-PH', {
              dateStyle: 'long',
              timeStyle: 'short',
          });

    const resetToNow = () => {
        const fresh = new Date();
        setSelectedDate(fresh.toISOString().substring(0, 10));
        setSelectedTime(
            `${String(fresh.getHours()).padStart(2, '0')}:${String(fresh.getMinutes()).padStart(2, '0')}`,
        );
    };

    const punch = (type: string) => {
        const label = typeLabel(type);
        const payload: Record<string, string | number | null> = {
            type,
        };

        if (enableCustomPunchTime) {
            payload.timestamp = `${selectedDate} ${selectedTime}:00`;
        }

        const sendPunch = () => {
            router.post('/payroll/attendance/punch', payload, {
                onSuccess: () => {
                    toast.success(`${label} recorded.`);
                },
                onError: (err: any) => {
                    toast.error(err.message ?? 'Failed to record punch.');
                },
            });
        };

        if (
            type === 'in' ||
            type === 'out' ||
            type === 'overtime_in' ||
            type === 'overtime_out'
        ) {
            if (!navigator.geolocation) {
                sendPunch();

                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    payload.latitude = position.coords.latitude;
                    payload.longitude = position.coords.longitude;
                    payload.accuracy_meters = Math.round(
                        position.coords.accuracy,
                    );
                    sendPunch();
                },
                () => {
                    toast.warning(
                        'Location not captured. Punch recorded without GPS.',
                        { position: 'top-center' },
                    );
                    sendPunch();
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
            );
        } else {
            sendPunch();
        }
    };

    const groupedLogs = groupTimeLogsByDate(recentTimeLogs);

    const [clock, setClock] = useState(new Date());
    useEffect(() => {
        const id = window.setInterval(() => setClock(new Date()), 1000);

        return () => window.clearInterval(id);
    }, []);

    const fmtClock = (ts: string) =>
        new Date(ts).toLocaleTimeString('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
        });

    const heroStatus = (() => {
        if (!punchState) {
            return { tone: 'neutral' as const, label: 'Loading…' };
        }

        if (punchState.is_complete) {
            return {
                tone: 'done' as const,
                label: 'Day complete',
                hint: punchState.last_punch
                    ? `Punched out at ${fmtClock(punchState.last_punch.timestamp)}`
                    : undefined,
            };
        }

        const last = punchState.last_punch;

        if (!last) {
            return {
                tone: 'idle' as const,
                label: 'Not punched in yet',
            };
        }

        const t = last.type;

        if (t === 'in') {
            return {
                tone: 'active' as const,
                label: `Punched in at ${fmtClock(last.timestamp)}`,
            };
        }

        if (t === 'lunch_out') {
            return {
                tone: 'paused' as const,
                label: `On break since ${fmtClock(last.timestamp)}`,
            };
        }

        if (t === 'lunch_in') {
            return {
                tone: 'active' as const,
                label: `Back from break at ${fmtClock(last.timestamp)}`,
            };
        }

        if (t === 'overtime_in') {
            return {
                tone: 'active' as const,
                label: `Overtime started at ${fmtClock(last.timestamp)}`,
            };
        }

        return {
            tone: 'neutral' as const,
            label: `${last.label} at ${fmtClock(last.timestamp)}`,
        };
    })();

    const disabledReason = (
        kind: 'in' | 'out' | 'lunch_out' | 'lunch_in' | 'overtime_in' | 'overtime_out',
    ): string | null => {
        if (!punchState) {
return null;
}

        const lastLabel = punchState.last_punch?.label;

        switch (kind) {
            case 'in':
                return punchState.can_punch_in ? null : 'Already punched in for today.';
            case 'out':
                return punchState.can_punch_out
                    ? null
                    : punchState.is_complete
                      ? 'Already punched out for today.'
                      : lastLabel === 'Start Break'
                        ? 'End your break before punching out.'
                        : 'Punch in first.';
            case 'lunch_out':
                return punchState.can_punch_lunch_out
                    ? null
                    : !punchState.last_punch
                      ? 'Punch in first.'
                      : 'Break already started or shift complete.';
            case 'lunch_in':
                return punchState.can_punch_lunch_in
                    ? null
                    : 'Start a break before ending it.';
            case 'overtime_in':
                return punchState.can_punch_overtime_in
                    ? null
                    : punchState.is_complete
                      ? 'Overtime already started.'
                      : 'Punch out before starting overtime.';
            case 'overtime_out':
                return punchState.can_punch_overtime_out
                    ? null
                    : 'Punch overtime in first.';
        }
    };

    const toneStyles: Record<string, string> = {
        neutral: 'border-border bg-sidebar text-foreground',
        idle: 'border-amber-200 bg-amber-50 text-amber-800',
        active: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        paused: 'border-yellow-200 bg-yellow-50 text-yellow-800',
        done: 'border-green-200 bg-green-50 text-green-800',
    };

    return (
        <div className="mx-auto w-full max-w-5xl space-y-6">
            <div
                className={`rounded-lg border p-4 sm:p-5 ${toneStyles[heroStatus.tone]}`}
            >
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <div className="font-mono text-3xl font-semibold tabular-nums sm:text-4xl">
                            {clock.toLocaleTimeString('en-PH', {
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit',
                            })}
                        </div>
                        <div className="text-xs opacity-80 sm:text-sm">
                            {clock.toLocaleDateString('en-PH', {
                                weekday: 'long',
                                month: 'long',
                                day: 'numeric',
                                year: 'numeric',
                            })}
                        </div>
                    </div>
                    <div className="flex items-center gap-2 text-sm font-medium">
                        {heroStatus.tone === 'done' && (
                            <CheckCircle2 className="h-4 w-4" />
                        )}
                        {heroStatus.tone === 'active' && (
                            <Clock className="h-4 w-4" />
                        )}
                        {heroStatus.tone === 'paused' && (
                            <Coffee className="h-4 w-4" />
                        )}
                        {heroStatus.tone === 'idle' && (
                            <MapPin className="h-4 w-4" />
                        )}
                        <div>
                            <div>{heroStatus.label}</div>
                            {heroStatus.hint && (
                                <div className="text-xs font-normal opacity-80">
                                    {heroStatus.hint}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-5">
                <div className="space-y-2 lg:col-span-2">
                    <h3 className="text-sm font-semibold">Recent Time Logs</h3>
                    {groupedLogs.length > 0 ? (
                        <div className="max-h-[480px] space-y-3 overflow-y-auto rounded-md border bg-sidebar p-3">
                            {groupedLogs.map(([date, logs]) => (
                                <div key={date}>
                                    <div className="mb-1 text-[11px] font-semibold text-muted-foreground uppercase">
                                        {new Date(date).toLocaleDateString(
                                            'en-PH',
                                            {
                                                weekday: 'short',
                                                month: 'short',
                                                day: 'numeric',
                                            },
                                        )}
                                    </div>
                                    <ul className="space-y-0.5">
                                        {logs.map((log) => (
                                            <li
                                                key={log.id}
                                                className="flex items-center justify-between rounded px-1.5 py-0.5 text-xs"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={`inline-block h-1.5 w-1.5 rounded-full ${
                                                            log.type === 'in'
                                                                ? 'bg-blue-500'
                                                                : log.type ===
                                                                    'out'
                                                                  ? 'bg-orange-500'
                                                                  : log.type ===
                                                                      'lunch_out'
                                                                    ? 'bg-yellow-500'
                                                                    : log.type ===
                                                                        'lunch_in'
                                                                      ? 'bg-purple-500'
                                                                      : 'bg-gray-400'
                                                        }`}
                                                    />
                                                    <span className="text-muted-foreground">
                                                        {typeLabel(log.type)}
                                                    </span>
                                                </div>
                                                <span className="font-mono text-[11px]">
                                                    {new Date(
                                                        log.timestamp,
                                                    ).toLocaleTimeString(
                                                        'en-PH',
                                                        {
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        },
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-md border bg-sidebar p-4 text-center text-sm text-muted-foreground">
                            No time logs in the last 10 days.
                        </div>
                    )}
                </div>

                <div className="space-y-4 lg:col-span-3">
                    {(firstInLog || lastOutLog) && (
                        <div className="flex items-center justify-center gap-3 rounded-md border bg-sidebar px-4 py-2 text-sm">
                            <span className="text-muted-foreground">In</span>
                            <span className="font-mono font-medium">
                                {firstInLog
                                    ? new Date(
                                          firstInLog.timestamp,
                                      ).toLocaleTimeString('en-PH', {
                                          hour: '2-digit',
                                          minute: '2-digit',
                                      })
                                    : '—'}
                            </span>
                            <span className="text-border">|</span>
                            <span className="text-muted-foreground">Out</span>
                            <span className="font-mono font-medium">
                                {lastOutLog
                                    ? new Date(
                                          lastOutLog.timestamp,
                                      ).toLocaleTimeString('en-PH', {
                                          hour: '2-digit',
                                          minute: '2-digit',
                                      })
                                    : '—'}
                            </span>
                        </div>
                    )}

                    {enableCustomPunchTime && (
                        <div className="space-y-2 rounded-md border bg-sidebar p-3">
                            <div className="flex items-center justify-between">
                                <h3 className="text-xs font-semibold text-muted-foreground uppercase">
                                    Set Punch Time
                                </h3>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-6 px-2 text-xs text-muted-foreground"
                                    onClick={resetToNow}
                                    type="button"
                                >
                                    <Clock className="mr-1 h-3 w-3" />
                                    Now
                                </Button>
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                <div className="space-y-1">
                                    <Label className="text-[10px] text-muted-foreground uppercase">
                                        Date
                                    </Label>
                                    <Input
                                        type="date"
                                        value={selectedDate}
                                        onChange={(e) =>
                                            setSelectedDate(e.target.value)
                                        }
                                        className="h-8 text-xs"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-[10px] text-muted-foreground uppercase">
                                        Time
                                    </Label>
                                    <Input
                                        type="time"
                                        value={selectedTime}
                                        onChange={(e) =>
                                            setSelectedTime(e.target.value)
                                        }
                                        className="h-8 text-xs"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="flex flex-col gap-2">
                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                size="lg"
                                onClick={() => setConfirmPunchType('in')}
                                disabled={disabledReason('in') !== null}
                                title={disabledReason('in') ?? undefined}
                                className="h-14 flex-col gap-1 border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700"
                            >
                                <LogIn className="h-4 w-4" />
                                <span className="text-xs">Punch In</span>
                            </Button>

                            <Button
                                size="lg"
                                onClick={() => setConfirmPunchType('out')}
                                disabled={disabledReason('out') !== null}
                                title={disabledReason('out') ?? undefined}
                                className="h-14 flex-col gap-1 border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700"
                            >
                                <LogOut className="h-4 w-4" />
                                <span className="text-xs">Punch Out</span>
                            </Button>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                size="lg"
                                variant="outline"
                                onClick={() => setConfirmPunchType('lunch_out')}
                                disabled={disabledReason('lunch_out') !== null}
                                title={disabledReason('lunch_out') ?? undefined}
                                className="h-14 flex-col gap-1"
                            >
                                <Coffee className="h-4 w-4" />
                                <span className="text-xs">Start Break</span>
                            </Button>

                            <Button
                                size="lg"
                                variant="outline"
                                onClick={() => setConfirmPunchType('lunch_in')}
                                disabled={disabledReason('lunch_in') !== null}
                                title={disabledReason('lunch_in') ?? undefined}
                                className="h-14 flex-col gap-1"
                            >
                                <UtensilsCrossed className="h-4 w-4" />
                                <span className="text-xs">End Break</span>
                            </Button>
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                size="lg"
                                onClick={() => setConfirmPunchType('overtime_in')}
                                disabled={disabledReason('overtime_in') !== null}
                                title={disabledReason('overtime_in') ?? undefined}
                                className="h-14 flex-col gap-1 border border-rose-600 bg-rose-600 text-white hover:bg-rose-700"
                            >
                                <PlusCircle className="h-4 w-4" />
                                <span className="text-xs">Overtime In</span>
                            </Button>

                            <Button
                                size="lg"
                                onClick={() => setConfirmPunchType('overtime_out')}
                                disabled={disabledReason('overtime_out') !== null}
                                title={disabledReason('overtime_out') ?? undefined}
                                className="h-14 flex-col gap-1 border border-rose-600 bg-rose-600 text-white hover:bg-rose-700"
                            >
                                <MinusCircle className="h-4 w-4" />
                                <span className="text-xs">Overtime Out</span>
                            </Button>
                        </div>
                    </div>

                    <AlertDialog
                        open={confirmPunchType !== null}
                        onOpenChange={(v) => !v && setConfirmPunchType(null)}
                    >
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Confirm{' '}
                                    {confirmPunchType
                                        ? typeLabel(confirmPunchType)
                                        : ''}
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    You are about to record{' '}
                                    {confirmPunchType === 'in'
                                        ? 'your time in'
                                        : confirmPunchType === 'out'
                                          ? 'your time out'
                                          : confirmPunchType === 'lunch_out'
                                            ? 'the start of your break'
                                            : confirmPunchType === 'lunch_in'
                                              ? 'the end of your break'
                                              : confirmPunchType ===
                                                  'overtime_in'
                                                ? 'your overtime start'
                                                : 'your overtime end'}{' '}
                                    at{' '}
                                    <span className="font-semibold text-foreground">
                                        {displayTime}
                                    </span>
                                    .
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() => {
                                        if (confirmPunchType) {
                                            punch(confirmPunchType);
                                        }
                                    }}
                                >
                                    Confirm
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>

                    {punchState?.logs?.length > 0 && (
                        <div className="rounded-md border bg-sidebar p-3">
                            <h3 className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                                Today's Punches
                            </h3>
                            <ul className="space-y-1">
                                {punchState.logs.map((log: any) => (
                                    <li
                                        key={log.id}
                                        className="flex justify-between text-sm"
                                    >
                                        <span className="text-muted-foreground">
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
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>

            <div className="space-y-2">
                <h3 className="text-sm font-semibold">Attendance History</h3>
                {attendanceSheets?.data && attendanceSheets.data.length > 0 ? (
                    <DataTable
                        columns={attendanceColumns}
                        pagination={attendanceSheets}
                    />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No attendance records found.
                    </p>
                )}
            </div>
        </div>
    );
}

function groupTimeLogsByDate(logs: TimeLogRow[]): [string, TimeLogRow[]][] {
    const groups = new Map<string, TimeLogRow[]>();

    for (const log of logs) {
        const d = new Date(log.timestamp);
        const date = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

        if (!groups.has(date)) {
            groups.set(date, []);
        }

        groups.get(date)!.push(log);
    }

    return [...groups.entries()].sort((a, b) => b[0].localeCompare(a[0]));
}

function typeLabel(type: string) {
    const map: Record<string, string> = {
        in: 'Punch In',
        lunch_out: 'Start Break',
        lunch_in: 'End Break',
        out: 'Punch Out',
        overtime_in: 'Overtime In',
        overtime_out: 'Overtime Out',
    };

    return map[type] ?? type;
}

const DAY_NAMES: Record<number, string> = {
    0: 'Sun',
    1: 'Mon',
    2: 'Tue',
    3: 'Wed',
    4: 'Thu',
    5: 'Fri',
    6: 'Sat',
};

function ProfileTab({
    employee,
    activeSchedule,
}: {
    employee: NonNullable<Props['employee']>;
    activeSchedule: Props['activeSchedule'];
}) {
    const { data, setData, put, processing, errors } = useForm({
        first_name: employee.first_name,
        last_name: employee.last_name,
        middle_name: employee.middle_name ?? '',
        email: employee.email ?? '',
        phone: employee.phone ?? '',
        address: employee.address ?? '',
        birth_date: toDateInput(employee.birth_date),
        sss_number: employee.sss_number ?? '',
        philhealth_number: employee.philhealth_number ?? '',
        pagibig_number: employee.pagibig_number ?? '',
        tin_number: employee.tin_number ?? '',
    });

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        put('/payroll/employee/profile', {
            onSuccess: () =>
                toast.success('Profile updated successfully.', {
                    position: 'top-center',
                }),
            onError: (err: any) =>
                toast.error(err.message ?? 'Failed to update profile.', {
                    position: 'top-center',
                }),
        });
    };

    return (
        <div className="space-y-6 pt-4">
            <form onSubmit={submit} className="space-y-6">
                <section className="space-y-3">
                    <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Personal Information
                    </h3>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-1">
                            <Label htmlFor="first_name">First Name *</Label>
                            <Input
                                id="first_name"
                                value={data.first_name}
                                onChange={(e) =>
                                    setData('first_name', e.target.value)
                                }
                                required
                            />
                            {errors.first_name && (
                                <p className="text-xs text-red-500">
                                    {errors.first_name}
                                </p>
                            )}
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="last_name">Last Name *</Label>
                            <Input
                                id="last_name"
                                value={data.last_name}
                                onChange={(e) =>
                                    setData('last_name', e.target.value)
                                }
                                required
                            />
                            {errors.last_name && (
                                <p className="text-xs text-red-500">
                                    {errors.last_name}
                                </p>
                            )}
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="middle_name">Middle Name</Label>
                            <Input
                                id="middle_name"
                                value={data.middle_name}
                                onChange={(e) =>
                                    setData('middle_name', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                            {errors.email && (
                                <p className="text-xs text-red-500">
                                    {errors.email}
                                </p>
                            )}
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="birth_date">Birth Date</Label>
                            <Input
                                id="birth_date"
                                type="date"
                                value={data.birth_date}
                                onChange={(e) =>
                                    setData('birth_date', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-1 sm:col-span-2">
                            <Label htmlFor="address">Address</Label>
                            <Textarea
                                id="address"
                                value={data.address}
                                onChange={(e) =>
                                    setData('address', e.target.value)
                                }
                                rows={2}
                            />
                        </div>
                    </div>
                </section>

                <section className="space-y-3 border-t pt-6">
                    <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Government IDs
                    </h3>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-1">
                            <Label htmlFor="sss_number">SSS Number</Label>
                            <Input
                                id="sss_number"
                                value={data.sss_number}
                                onChange={(e) =>
                                    setData('sss_number', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="philhealth_number">
                                PhilHealth Number
                            </Label>
                            <Input
                                id="philhealth_number"
                                value={data.philhealth_number}
                                onChange={(e) =>
                                    setData('philhealth_number', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="pagibig_number">
                                Pag-IBIG Number
                            </Label>
                            <Input
                                id="pagibig_number"
                                value={data.pagibig_number}
                                onChange={(e) =>
                                    setData('pagibig_number', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="tin_number">TIN</Label>
                            <Input
                                id="tin_number"
                                value={data.tin_number}
                                onChange={(e) =>
                                    setData('tin_number', e.target.value)
                                }
                            />
                        </div>
                    </div>
                </section>

                <section className="space-y-3 border-t pt-6">
                    <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Employment Details
                    </h3>
                    <div className="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span className="text-muted-foreground">
                                Employee #:
                            </span>{' '}
                            <span className="font-mono font-medium">
                                {employee.employee_number}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Branch:
                            </span>{' '}
                            <span className="font-medium">
                                {employee.branch?.name ?? '—'}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Position:
                            </span>{' '}
                            <span className="font-medium capitalize">
                                {employee.position}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Status:
                            </span>{' '}
                            <span className="font-medium capitalize">
                                {employee.status}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Daily Rate:
                            </span>{' '}
                            <span className="font-mono font-medium">
                                {formatCurrency(employee.current_daily_rate)}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Hire Date:
                            </span>{' '}
                            <span className="font-medium">
                                {employee.hire_date}
                            </span>
                        </div>
                        <div className="col-span-2">
                            <span className="text-muted-foreground">
                                Shift:
                            </span>{' '}
                            <span className="font-medium">
                                {activeSchedule
                                    ? `${formatTime(activeSchedule.start_time)} – ${formatTime(activeSchedule.end_time)}`
                                    : '—'}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Rest Days:
                            </span>{' '}
                            <span className="font-medium">
                                {activeSchedule?.rest_days?.length
                                    ? activeSchedule.rest_days
                                          .map((d: number) => DAY_NAMES[d])
                                          .join(', ')
                                    : '—'}
                            </span>
                        </div>
                    </div>
                </section>

                <div className="sticky bottom-0 -mx-4 border-t bg-background/95 px-4 py-3 backdrop-blur">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full"
                    >
                        Save Changes
                    </Button>
                </div>
            </form>
        </div>
    );
}
