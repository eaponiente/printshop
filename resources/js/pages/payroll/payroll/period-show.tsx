import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { AlertTriangle, ArrowLeft, CheckCircle, ClipboardCheck, RefreshCw, Trash2, Undo2 } from 'lucide-react';
import { useState } from 'react';
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
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Payroll Periods', href: '/payroll/periods' },
];

type PeriodItem = {
    id: number;
    employee_id: number;
    employee: {
        id: number;
        first_name: string;
        last_name: string;
        employee_number: string;
        current_daily_rate: number;
    };
    total_regular_days: number;
    absent_days: number;
    total_late_minutes: number;
    late_deduction: number;
    total_undertime_minutes: number;
    undertime_deduction: number;
    fine_deduction: number;
    total_overtime_minutes: number;
    overtime_pay: number;
    holiday_pay_days: number;
    holiday_pay: number;
    gross_pay: number;
    deminimis_earnings: number;
    sss_deduction: number;
    philhealth_deduction: number;
    pagibig_deduction: number;
    ca_deduction: number;
    net_pay: number;
};

type IncompleteSheet = {
    employee: string;
    employee_id: number;
    date: string;
    reason: string;
};

type Props = {
    period: {
        id: number;
        branch: { name: string };
        period_start: string;
        period_end: string;
        status: 'draft' | 'approved' | 'paid' | 'voided';
        approved_at: string | null;
        checked_at: string | null;
    };
    items: PaginatedResponse<PeriodItem>;
    incompleteSheets: IncompleteSheet[];
    canApprove: boolean;
    canDelete: boolean;
    isSuperAdmin: boolean;
};

export default function PayrollPeriodShow({
    period,
    items,
    incompleteSheets,
    canApprove,
    canDelete,
    isSuperAdmin,
}: Props) {
    const [recomputing, setRecomputing] = useState(false);

    const columns: ColumnDef<PeriodItem>[] = [
        {
            accessorKey: 'employee',
            header: 'Employee',
            cell: ({ row }: CellContext<PeriodItem, any>) => (
                <div>
                    <div className="font-medium">
                        <button
                            className="text-left hover:underline"
                            onClick={() =>
                                router.get(
                                    `/payroll/periods/${period.id}/payslip/${row.original.id}`,
                                )
                            }
                        >
                            {row.original.employee?.last_name},{' '}
                            {row.original.employee?.first_name}
                        </button>
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {row.original.employee?.employee_number}
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'total_regular_days',
            header: 'Days',
            cell: ({ row }: CellContext<PeriodItem, any>) => (
                <span className="text-sm">
                    {row.original.total_regular_days}d
                </span>
            ),
        },
        {
            accessorKey: 'gross_pay',
            header: 'Gross',
            cell: ({ row }: CellContext<PeriodItem, any>) => (
                <span className="font-mono text-sm">
                    {formatCurrency(row.original.gross_pay)}
                </span>
            ),
        },
        {
            header: 'Deductions',
            cell: ({ row }: CellContext<PeriodItem, any>) => {
                // Statutory + cash advance only. Late, undertime, and fine
                // deductions are already inside `gross_pay` via daily_wage and
                // would be double-counted if added here.
                const total =
                    (Number(row.original.sss_deduction) || 0) +
                    (Number(row.original.philhealth_deduction) || 0) +
                    (Number(row.original.pagibig_deduction) || 0) +
                    (Number(row.original.ca_deduction) || 0);

                return (
                    <span className="font-mono text-sm text-red-600">
                        -{formatCurrency(total)}
                    </span>
                );
            },
        },
        {
            accessorKey: 'net_pay',
            header: 'Net Pay',
            cell: ({ row }: CellContext<PeriodItem, any>) => (
                <span className="font-mono text-sm font-bold">
                    {formatCurrency(row.original.net_pay)}
                </span>
            ),
        },
    ];

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Period Detail" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.get('/payroll/periods')}
                        >
                            <ArrowLeft className="mr-1 h-4 w-4" /> Back
                        </Button>
                        <h1 className="text-xl font-semibold">
                            {period.branch.name} — {period.period_start} to{' '}
                            {period.period_end}
                        </h1>
                    </div>
                    <div className="flex items-center gap-2">
                        {canApprove && period.status === 'draft' && (
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={recomputing}
                                onClick={() =>
                                    router.post(
                                        `/payroll/periods/${period.id}/recompute`,
                                        {},
                                        {
                                            onStart: () => setRecomputing(true),
                                            onFinish: () =>
                                                setRecomputing(false),
                                            onSuccess: () =>
                                                toast.success(
                                                    'Payroll recomputed.',
                                                ),
                                            onError: () =>
                                                toast.error(
                                                    'Recompute failed.',
                                                ),
                                        },
                                    )
                                }
                            >
                                <RefreshCw
                                    className={`mr-1 h-4 w-4 ${recomputing ? 'animate-spin' : ''}`}
                                />
                                {recomputing ? 'Recomputing…' : 'Recompute'}
                            </Button>
                        )}
                        {canApprove && period.status === 'draft' && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        `/payroll/periods/${period.id}/check`,
                                        {},
                                        {
                                            onError: () =>
                                                toast.error('Check failed.'),
                                        },
                                    )
                                }
                            >
                                <ClipboardCheck className="mr-1 h-4 w-4" />
                                Check Payroll
                            </Button>
                        )}
                        {canApprove && period.status === 'draft' && period.checked_at && (
                            <Button
                                variant="default"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        `/payroll/periods/${period.id}/approve`,
                                        {},
                                        {
                                            onSuccess: () =>
                                                toast.success('Approved.'),
                                            onError: () =>
                                                toast.error('Failed.'),
                                        },
                                    )
                                }
                            >
                                <CheckCircle className="mr-1 h-4 w-4" /> Approve
                            </Button>
                        )}
                        {canDelete && period.status === 'draft' && (
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="destructive" size="sm">
                                        <Trash2 className="mr-1 h-4 w-4" />{' '}
                                        Delete
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Delete draft period?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            This will delete the draft payroll
                                            period and unlock all attendance
                                            sheets. This cannot be undone.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancel
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() =>
                                                router.delete(
                                                    `/payroll/periods/${period.id}`,
                                                    {
                                                        onSuccess: () =>
                                                            toast.success(
                                                                'Period deleted.',
                                                                {
                                                                    position:
                                                                        'top-center',
                                                                },
                                                            ),
                                                        onError: (err: any) =>
                                                            toast.error(
                                                                err.message ??
                                                                    'Deletion failed.',
                                                                {
                                                                    position:
                                                                        'top-center',
                                                                },
                                                            ),
                                                    },
                                                )
                                            }
                                        >
                                            Delete
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        )}
                        {isSuperAdmin &&
                            (period.status === 'approved' ||
                                period.status === 'paid') && (
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() =>
                                        router.post(
                                            `/payroll/periods/${period.id}/void`,
                                            {},
                                            {
                                                onSuccess: () =>
                                                    toast.success('Voided.'),
                                                onError: () =>
                                                    toast.error('Failed.'),
                                            },
                                        )
                                    }
                                >
                                    <Undo2 className="mr-1 h-4 w-4" /> Void
                                </Button>
                            )}
                    </div>
                </div>

                {period.checked_at && (
                    <div
                        className={`rounded-md border p-4 text-sm ${
                            incompleteSheets.length > 0
                                ? 'border-amber-200 bg-amber-50'
                                : 'border-green-200 bg-green-50'
                        }`}
                    >
                        <div className="mb-1 flex items-center gap-2 font-semibold">
                            {incompleteSheets.length > 0 ? (
                                <>
                                    <AlertTriangle className="h-4 w-4 text-amber-600" />
                                    <span className="text-amber-700">
                                        {incompleteSheets.length} incomplete attendance record
                                        {incompleteSheets.length !== 1 ? 's' : ''} found
                                    </span>
                                </>
                            ) : (
                                <>
                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                    <span className="text-green-700">
                                        All attendance records are complete
                                    </span>
                                </>
                            )}
                        </div>
                        {incompleteSheets.length > 0 && (
                            <ul className="mt-2 space-y-1">
                                {incompleteSheets.map((s, i) => (
                                    <li
                                        key={i}
                                        className="flex items-center gap-2 text-amber-800"
                                    >
                                        <span className="font-medium">
                                            {s.employee}
                                        </span>
                                        <span className="text-amber-500">—</span>
                                        <span className="font-mono text-xs">
                                            {s.date}
                                        </span>
                                        <span className="text-amber-600">
                                            {s.reason}
                                        </span>
                                        <a
                                            href={`/payroll/attendance-sheets/${s.employee_id}?date=${s.date}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="ml-auto text-xs text-blue-600 underline hover:text-blue-800"
                                        >
                                            View attendance →
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="mt-2 text-xs text-muted-foreground">
                            Last checked:{' '}
                            {new Date(period.checked_at).toLocaleString()}
                        </p>
                    </div>
                )}

                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={items} />
                </div>
            </div>
        </PayrollLayout>
    );
}
