import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, CalendarDays } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Attendance Sheets', href: '/payroll/attendance-sheets' },
];

type Employee = {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    branch_id: number;
};

type Sheet = {
    id: number;
    employee_id: number;
    date: string;
    hours_worked: number;
    late_minutes: number;
    late_deduction: number;
    undertime_minutes: number;
    overtime_minutes: number;
    holiday_pay: number;
    overtime_pay: number;
    daily_wage: number;
    fine_deduction: number;
    is_present: boolean;
    is_rest_day: boolean;
    absence_type: string | null;
    holiday_type: string | null;
    holiday_pay_percent: number | null;
    locked_at: string | null;
    leave_type: string | null;
    leave_duration: string | null;
    leave_is_paid: boolean;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Props = {
    employees: {
        data: Employee[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    weekStart: string;
    weekEnd: string;
    currentDate: string;
    sheets: Record<string, Sheet>;
    branches: { id: number; name: string }[];
    selectedBranch: number | null;
    isSuperAdmin: boolean;
};

const statusBadge = (sheet: Sheet | undefined) => {
    if (!sheet) {
        return { text: '—', className: 'text-gray-400' };
    }

    if (sheet.is_rest_day) {
        return { text: 'Rest', className: 'text-blue-500' };
    }

    if (sheet.leave_type) {
        return { text: 'Leave', className: 'text-teal-600' };
    }

    if (sheet.is_present) {
        return { text: 'Present', className: 'text-green-600' };
    }

    if (sheet.holiday_pay_percent !== null && sheet.holiday_pay_percent > 0) {
        return { text: 'Holiday', className: 'text-amber-600' };
    }

    if (sheet.absence_type === 'unexcused') {
        return { text: 'Absent', className: 'text-red-500' };
    }

    return { text: 'Absent', className: 'text-red-500' };
};

const getWeekDates = (weekStart: string) => {
    const dates: string[] = [];
    const start = new Date(weekStart);

    for (let i = 0; i < 7; i++) {
        const d = new Date(start);
        d.setDate(d.getDate() + i);
        dates.push(d.toISOString().substring(0, 10));
    }

    return dates;
};

const dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export default function AttendanceSheets({
    employees,
    weekStart,
    weekEnd,
    currentDate,
    sheets,
    branches,
    selectedBranch,
    isSuperAdmin,
}: Props) {
    const weekDates = getWeekDates(weekStart);
    const empList = employees.data;

    const navigate = (params: Record<string, string | number | undefined>) => {
        router.get('/payroll/attendance-sheets', params);
    };

    const prevWeek = () => {
        const d = new Date(weekStart);
        d.setDate(d.getDate() - 7);
        navigate({
            date: d.toISOString().substring(0, 10),
            branch_id: selectedBranch ?? undefined,
        });
    };

    const nextWeek = () => {
        const d = new Date(weekStart);
        d.setDate(d.getDate() + 7);
        navigate({
            date: d.toISOString().substring(0, 10),
            branch_id: selectedBranch ?? undefined,
        });
    };

    const goToPage = (url: string | null) => {
        if (!url) return;
        const parsed = new URL(url, window.location.origin);
        navigate({
            date: parsed.searchParams.get('date') ?? currentDate,
            branch_id:
                parsed.searchParams.get('branch_id') ??
                selectedBranch ??
                undefined,
            page: parsed.searchParams.get('page') ?? undefined,
        });
    };

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Attendance Sheets" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Attendance Sheets
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {weekStart} to {weekEnd}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {isSuperAdmin && branches.length > 0 && (
                            <Select
                                value={
                                    selectedBranch
                                        ? String(selectedBranch)
                                        : '__all__'
                                }
                                onValueChange={(val) =>
                                    navigate({
                                        date: currentDate,
                                        branch_id:
                                            val === '__all__' ? undefined : val,
                                    })
                                }
                            >
                                <SelectTrigger className="h-8 w-[160px] text-xs">
                                    <SelectValue placeholder="All branches" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all__">
                                        All branches
                                    </SelectItem>
                                    {branches.map((b) => (
                                        <SelectItem
                                            key={b.id}
                                            value={String(b.id)}
                                        >
                                            {b.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        <Button variant="outline" size="sm" onClick={prevWeek}>
                            <ArrowLeft className="mr-1 h-4 w-4" /> Prev
                        </Button>
                        <Button variant="outline" size="sm" onClick={nextWeek}>
                            Next <ArrowRight className="ml-1 h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-md border border-sidebar-border bg-sidebar">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="sticky left-0 bg-muted/50 px-3 py-2 text-left font-medium">
                                    Employee
                                </th>
                                {weekDates.map((date, i) => (
                                    <th
                                        key={date}
                                        className="px-3 py-2 text-center font-medium"
                                    >
                                        <div>{dayLabels[i]}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {date.substring(5)}
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {empList.map((emp) => (
                                <tr key={emp.id} className="border-b">
                                    <td className="sticky left-0 bg-sidebar px-3 py-2 font-medium">
                                        {emp.last_name}, {emp.first_name}
                                    </td>
                                    {weekDates.map((date) => {
                                        const key = `${emp.id}-${date}`;
                                        const sheet = sheets[key];
                                        const status = statusBadge(sheet);

                                        return (
                                            <td
                                                key={date}
                                                className="px-2 py-2 text-center"
                                            >
                                                {sheet ? (
                                                    <button
                                                        type="button"
                                                        className="block w-full cursor-pointer rounded border border-dashed border-muted-foreground/25 p-1 text-center hover:border-muted-foreground/50 hover:bg-accent/50"
                                                        onClick={() =>
                                                            router.get(
                                                                `/payroll/attendance-sheets/${emp.id}?date=${date}`,
                                                            )
                                                        }
                                                    >
                                                        <span
                                                            className={`text-xs ${status.className}`}
                                                        >
                                                            {status.text}
                                                        </span>
                                                        {sheet.is_present && (
                                                            <div className="mt-0.5 font-mono text-xs">
                                                                {formatCurrency(
                                                                    sheet.daily_wage,
                                                                )}
                                                            </div>
                                                        )}
                                                        {sheet.leave_type && (
                                                            <div className="mt-0.5 text-xs text-teal-600">
                                                                {sheet.leave_duration ===
                                                                'full'
                                                                    ? 'Full Leave'
                                                                    : 'Leave'}
                                                            </div>
                                                        )}
                                                        {sheet.late_minutes >
                                                            0 && (
                                                            <div className="mt-0.5 text-[10px] text-red-600">
                                                                Late{' '}
                                                                {
                                                                    sheet.late_minutes
                                                                }
                                                                m
                                                            </div>
                                                        )}
                                                        {sheet.undertime_minutes >
                                                            0 && (
                                                            <div className="mt-0.5 text-[10px] text-orange-600">
                                                                UT{' '}
                                                                {
                                                                    sheet.undertime_minutes
                                                                }
                                                                m
                                                            </div>
                                                        )}
                                                        {sheet.overtime_minutes >
                                                            0 && (
                                                            <div className="mt-0.5 text-[10px] text-blue-600">
                                                                OT{' '}
                                                                {
                                                                    sheet.overtime_minutes
                                                                }
                                                                m
                                                            </div>
                                                        )}
                                                        {sheet.fine_deduction >
                                                            0 && (
                                                            <div className="mt-0.5 text-[10px] text-red-600">
                                                                Fined
                                                            </div>
                                                        )}
                                                    </button>
                                                ) : (
                                                    <span
                                                        className={`text-xs ${status.className}`}
                                                    >
                                                        {status.text}
                                                    </span>
                                                )}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {(employees.prev_page_url || employees.next_page_url) && (
                    <div className="flex items-center justify-between">
                        <span className="text-xs text-muted-foreground">
                            Page {employees.prev_page_url ? '...' : '1'}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!employees.prev_page_url}
                                onClick={() =>
                                    goToPage(employees.prev_page_url)
                                }
                            >
                                <ArrowLeft className="mr-1 h-3.5 w-3.5" /> Prev
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!employees.next_page_url}
                                onClick={() =>
                                    goToPage(employees.next_page_url)
                                }
                            >
                                Next <ArrowRight className="ml-1 h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </PayrollLayout>
    );
}
