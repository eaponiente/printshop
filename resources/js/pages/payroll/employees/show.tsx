import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Employee } from '@/types/employee';
import { toManilaTime } from '@/utils/dateHelper';
import { formatCurrency } from '@/utils/formatters';
import { ScheduleManager } from './components/schedule-manager';

interface ShowProps {
    employee: Employee;
    daysOfWeek: Array<{ value: number; label: string }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Employees', href: '/payroll/employees' },
    { title: 'View', href: '#' },
];

const statusBadge = (status: string) => {
    const map: Record<string, string> = {
        active: 'bg-green-100 text-green-700 border-green-200',
        inactive: 'bg-gray-100 text-gray-700 border-gray-200',
        resigned: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        terminated: 'bg-red-100 text-red-700 border-red-200',
    };

    return map[status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
};

const positionBadge = (position: string) => {
    const labels: Record<string, string> = {
        regular: 'Regular',
        probation: 'Probation',
    };

    return labels[position] ?? position;
};

export default function EmployeeShow({ employee, daysOfWeek }: ShowProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={employee.full_name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link href="/payroll/employees">
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-xl font-semibold">
                                {employee.full_name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {employee.employee_number}
                            </p>
                        </div>
                    </div>
                    <Link href={`/payroll/employees/${employee.id}/edit`}>
                        <Button variant="outline">
                            <Pencil className="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div className="rounded-md border border-sidebar-border bg-sidebar p-6">
                        <h2 className="mb-4 text-sm font-semibold text-muted-foreground uppercase">
                            Personal Information
                        </h2>
                        <dl className="space-y-3">
                            <InfoRow
                                label="Full Name"
                                value={employee.full_name}
                            />
                            <InfoRow
                                label="Email"
                                value={employee.email ?? '--'}
                            />
                            <InfoRow
                                label="Phone"
                                value={employee.phone ?? '--'}
                            />
                            <InfoRow
                                label="Address"
                                value={employee.address ?? '--'}
                            />
                            <InfoRow
                                label="Birth Date"
                                value={
                                    employee.birth_date
                                        ? toManilaTime(employee.birth_date)
                                        : '--'
                                }
                            />
                        </dl>
                    </div>

                    <div className="rounded-md border border-sidebar-border bg-sidebar p-6">
                        <h2 className="mb-4 text-sm font-semibold text-muted-foreground uppercase">
                            Employment Details
                        </h2>
                        <dl className="space-y-3">
                            <InfoRow
                                label="Employee Number"
                                value={employee.employee_number}
                            />
                            <InfoRow
                                label="Branch"
                                value={employee.branch?.name ?? '--'}
                            />
                            <InfoRow
                                label="Position"
                                value={
                                    <span
                                        className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium`}
                                    >
                                        {positionBadge(employee.position)}
                                    </span>
                                }
                            />
                            <InfoRow
                                label="Status"
                                value={
                                    <span
                                        className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${statusBadge(employee.status)}`}
                                    >
                                        {employee.status}
                                    </span>
                                }
                            />
                            <InfoRow
                                label="Hire Date"
                                value={toManilaTime(employee.hire_date)}
                            />
                            <InfoRow
                                label="End Date"
                                value={
                                    employee.end_date
                                        ? toManilaTime(employee.end_date)
                                        : '--'
                                }
                            />
                            <InfoRow
                                label="Daily Rate"
                                value={formatCurrency(
                                    employee.current_daily_rate,
                                )}
                            />
                            <InfoRow
                                label="Default Paid Leaves"
                                value={String(
                                    employee.default_paid_leave_days ?? 5,
                                )}
                            />
                            <InfoRow
                                label="Leave Balance"
                                value={String(employee.paid_leave_balance ?? 5)}
                            />
                        </dl>
                    </div>

                    <div className="rounded-md border border-sidebar-border bg-sidebar p-6">
                        <h2 className="mb-4 text-sm font-semibold text-muted-foreground uppercase">
                            Government IDs
                        </h2>
                        <dl className="space-y-3">
                            <InfoRow
                                label="SSS"
                                value={employee.sss_number ?? '--'}
                            />
                            <InfoRow
                                label="PhilHealth"
                                value={employee.philhealth_number ?? '--'}
                            />
                            <InfoRow
                                label="Pag-IBIG"
                                value={employee.pagibig_number ?? '--'}
                            />
                            <InfoRow
                                label="TIN"
                                value={employee.tin_number ?? '--'}
                            />
                        </dl>
                    </div>

                    {employee.salaries && employee.salaries.length > 0 && (
                        <div className="rounded-md border border-sidebar-border bg-sidebar p-6">
                            <h2 className="mb-4 text-sm font-semibold text-muted-foreground uppercase">
                                Salary History
                            </h2>
                            <div className="space-y-2">
                                {employee.salaries.map((s) => (
                                    <div
                                        key={s.id}
                                        className={`flex items-center justify-between rounded border px-3 py-2 text-sm ${s.end_date ? 'border-muted bg-muted/30' : 'border-green-200 bg-green-50'}`}
                                    >
                                        <div>
                                            <span className="font-mono font-medium">
                                                {formatCurrency(s.daily_rate)}
                                            </span>
                                            <span className="mx-2 text-muted-foreground">
                                                /day
                                            </span>
                                            {!s.end_date && (
                                                <span className="ml-2 rounded bg-green-200 px-1.5 py-0.5 text-xs font-medium text-green-800">
                                                    Current
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {toManilaTime(s.effective_date)}
                                            {s.end_date &&
                                                ` — ${toManilaTime(s.end_date)}`}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="rounded-md border border-sidebar-border bg-sidebar p-6 lg:col-span-2">
                        <ScheduleManager
                            employeeId={employee.id}
                            schedules={(employee as any).schedules ?? []}
                            daysOfWeek={daysOfWeek}
                        />
                    </div>

                    {employee.notes && (
                        <div className="rounded-md border border-sidebar-border bg-sidebar p-6 lg:col-span-2">
                            <h2 className="mb-4 text-sm font-semibold text-muted-foreground uppercase">
                                Notes
                            </h2>
                            <p className="text-sm whitespace-pre-wrap">
                                {employee.notes}
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-4 border-b border-muted pb-2 last:border-0">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}
