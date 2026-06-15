import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    Clock,
    Coffee,
    Lock,
    LogIn,
    LogOut,
    User,
    UtensilsCrossed,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { toDateInput } from '@/utils/dateHelper';
import { formatCurrency, formatTime } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'My Attendance', href: '/payroll/attendance' },
];

type Props = {
    tab: string;
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
    weekSheets: any[];
    recentTimeLogs: any[];
    weekStart: string;
    weekEnd: string;
};

type Tab = 'punch' | 'history' | 'profile';

const TAB_PROPS: Partial<Record<Tab, string[]>> = {
    history: ['weekSheets', 'recentTimeLogs', 'tab'],
};

export default function MyAttendance(props: Props) {
    const { punchState, employee } = props;
    const [tab, setTabState] = useState<Tab>((props.tab as Tab) ?? 'punch');
    const [loadedTabs, setLoadedTabs] = useState<Set<Tab>>(
        new Set(['punch', 'profile']),
    );

    const switchTab = (key: Tab) => {
        setTabState(key);
        const needed = TAB_PROPS[key];

        if (needed && !loadedTabs.has(key)) {
            setLoadedTabs((prev) => new Set(prev).add(key));
            router.reload({
                only: needed,
                data: { tab: key },
                onSuccess: () => {
                    window.history.replaceState(null, '', `?tab=${key}`);
                },
            });
        } else {
            window.history.replaceState(null, '', `?tab=${key}`);
        }
    };

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
                <div>
                    <h1 className="text-xl font-semibold">My Attendance</h1>
                    <p className="text-sm text-muted-foreground">
                        {employee.full_name}
                    </p>
                </div>

                <div className="flex gap-1 overflow-x-auto rounded-md border bg-sidebar p-1">
                    {(
                        [
                            ['punch', 'Punch', Clock],
                            ['history', 'History', CalendarDays],
                            ['profile', 'Profile', User],
                        ] as [Tab, string, any][]
                    ).map(([key, label, Icon]) => (
                        <button
                            key={key}
                            onClick={() => switchTab(key)}
                            className={`flex items-center gap-1.5 rounded px-3 py-1.5 text-xs font-medium transition-colors ${
                                tab === key
                                    ? 'bg-accent text-accent-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <Icon className="h-3.5 w-3.5" />
                            {label}
                        </button>
                    ))}
                </div>

                {tab === 'punch' && (
                    <PunchTab
                        punchState={punchState}
                        weekSheets={props.weekSheets}
                    />
                )}
                {tab === 'history' && (
                    <HistoryTab
                        weekSheets={props.weekSheets}
                        timeLogs={props.recentTimeLogs}
                        weekStart={props.weekStart}
                        weekEnd={props.weekEnd}
                    />
                )}
                {tab === 'profile' && props.employee && (
                    <ProfileTab
                        employee={props.employee}
                        activeSchedule={props.activeSchedule}
                    />
                )}
            </div>
        </PayrollLayout>
    );
}

function PunchTab({
    punchState,
    weekSheets,
}: {
    punchState: any;
    weekSheets: any[];
}) {
    const today = new Date().toISOString().substring(0, 10);
    const todaySheet = weekSheets?.find((s: any) => s.date === today);
    const isLocked = todaySheet?.locked_at != null;

    const punch = (type: string) => {
        const label = typeLabel(type);
        const payload: Record<string, string | number | null> = {
            type,
        };

        const sendPunch = () => {
            router.post('/payroll/attendance/punch', payload, {
                onSuccess: () => toast.success(`${label} recorded.`),
                onError: (err: any) =>
                    toast.error(err.message ?? 'Failed to record punch.'),
            });
        };

        // Only capture geolocation for IN and OUT punches
        if (type === 'in' || type === 'out') {
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
                    // Permission denied or error — still allow the punch
                    sendPunch();
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
            );
        } else {
            sendPunch();
        }
    };

    return (
        <div className="mx-auto max-w-sm space-y-4">
            {punchState?.last_punch && (
                <div className="rounded-md border bg-sidebar p-3 text-sm">
                    Last punch:{' '}
                    <span className="font-medium">
                        {punchState.last_punch.label}
                    </span>{' '}
                    at{' '}
                    <span className="font-medium">
                        {new Date(
                            punchState.last_punch.timestamp,
                        ).toLocaleTimeString('en-PH', {
                            hour: '2-digit',
                            minute: '2-digit',
                        })}
                    </span>
                </div>
            )}

            {punchState?.is_complete && (
                <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-700">
                    All punches complete for today.
                </div>
            )}

            {isLocked && (
                <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm font-medium text-amber-700">
                    <div className="flex items-center gap-2">
                        <Lock className="h-4 w-4" />
                        Attendance is locked (payroll generated). Punching is
                        disabled.
                    </div>
                </div>
            )}

            <div className="space-y-2">
                <Button
                    size="lg"
                    variant="outline"
                    onClick={() => punch('in')}
                    disabled={isLocked}
                    className="h-16 w-full flex-row gap-3"
                >
                    <LogIn className="h-5 w-5" />
                    <span>Punch In</span>
                </Button>

                <Button
                    size="lg"
                    variant="outline"
                    onClick={() => punch('lunch_out')}
                    disabled={isLocked}
                    className="h-16 w-full flex-row gap-3"
                >
                    <Coffee className="h-5 w-5" />
                    <span>Lunch Out</span>
                </Button>

                <Button
                    size="lg"
                    variant="outline"
                    onClick={() => punch('lunch_in')}
                    disabled={isLocked}
                    className="h-16 w-full flex-row gap-3"
                >
                    <UtensilsCrossed className="h-5 w-5" />
                    <span>Lunch In</span>
                </Button>

                <Button
                    size="lg"
                    variant="outline"
                    onClick={() => punch('out')}
                    disabled={isLocked}
                    className="h-16 w-full flex-row gap-3"
                >
                    <LogOut className="h-5 w-5" />
                    <span>Punch Out</span>
                </Button>
            </div>

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
                                    {new Date(log.timestamp).toLocaleTimeString(
                                        'en-PH',
                                        {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                            second: '2-digit',
                                        },
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

function HistoryTab({
    weekSheets,
    timeLogs,
    weekStart,
    weekEnd,
}: {
    weekSheets: any[];
    timeLogs: any[];
    weekStart: string;
    weekEnd: string;
}) {
    return (
        <div className="space-y-6">
            <div className="space-y-2">
                <h3 className="text-sm font-semibold">
                    This Week ({weekStart} – {weekEnd})
                </h3>
                {weekSheets?.length > 0 ? (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50">
                                    <th className="px-3 py-2 text-left">
                                        Date
                                    </th>
                                    <th className="px-3 py-2 text-center">
                                        Status
                                    </th>
                                    <th className="px-3 py-2 text-center">
                                        Hours
                                    </th>
                                    <th className="px-3 py-2 text-center">
                                        Late
                                    </th>
                                    <th className="px-3 py-2 text-center">
                                        UT
                                    </th>
                                    <th className="px-3 py-2 text-center">
                                        OT
                                    </th>
                                    <th className="px-3 py-2 text-center">
                                        Holiday
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Wage
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {weekSheets.map((s: any) => (
                                    <tr key={s.date} className="border-b">
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {new Date(
                                                s.date,
                                            ).toLocaleDateString('en-PH', {
                                                weekday: 'short',
                                                month: 'short',
                                                day: 'numeric',
                                            })}
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            {s.is_present ? (
                                                <span className="text-xs font-medium text-green-600">
                                                    Present
                                                </span>
                                            ) : s.is_rest_day ? (
                                                <span className="text-xs text-blue-500">
                                                    Rest
                                                </span>
                                            ) : (
                                                <span className="text-xs text-red-500">
                                                    Absent
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-center font-mono text-xs">
                                            {s.hours_worked}
                                        </td>
                                        <td className="px-3 py-2 text-center text-xs">
                                            {s.late_minutes > 0
                                                ? `${s.late_minutes}m`
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-center text-xs">
                                            {s.undertime_minutes > 0
                                                ? `${s.undertime_minutes}m`
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-center text-xs">
                                            {s.overtime_minutes > 0
                                                ? `${s.overtime_minutes}m`
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-center text-xs">
                                            {s.holiday_pay_percent
                                                ? `${s.holiday_pay_percent}%`
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-xs font-medium">
                                            {formatCurrency(s.daily_wage)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No attendance records this week.
                    </p>
                )}
            </div>

            {timeLogs?.length > 0 && (
                <div className="space-y-2">
                    <h3 className="text-sm font-semibold">Recent Time Logs</h3>
                    <div className="space-y-1">
                        {timeLogs.map((log: any) => (
                            <div
                                key={log.id}
                                className="flex items-center justify-between rounded border bg-sidebar px-3 py-2 text-sm"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="font-mono text-xs">
                                        {log.timestamp?.substring(0, 10)}
                                    </span>
                                    <span className="text-xs capitalize">
                                        {typeLabel(log.type)}
                                    </span>
                                    <span className="text-xs text-muted-foreground uppercase">
                                        {log.source}
                                    </span>
                                </div>
                                <span className="font-mono text-xs">
                                    {new Date(log.timestamp).toLocaleTimeString(
                                        'en-PH',
                                        { hour: '2-digit', minute: '2-digit' },
                                    )}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
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
        <div className="space-y-6">
            <form onSubmit={submit} className="space-y-6">
                <div className="rounded-md border bg-sidebar p-4">
                    <h3 className="mb-3 text-xs font-semibold text-muted-foreground uppercase">
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
                </div>

                <div className="rounded-md border bg-sidebar p-4">
                    <h3 className="mb-3 text-xs font-semibold text-muted-foreground uppercase">
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
                </div>

                <div className="rounded-md border bg-sidebar p-4">
                    <h3 className="mb-3 text-xs font-semibold text-muted-foreground uppercase">
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
                </div>

                <Button type="submit" disabled={processing}>
                    Save Changes
                </Button>
            </form>
        </div>
    );
}
