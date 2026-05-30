import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Check, X } from 'lucide-react';
import { toast } from 'sonner';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';

type Props = { requests: PaginatedResponse<any> };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Leave Requests', href: '/payroll/leave-requests' },
];
const statusBadge = (s: string) =>
    ({
        approved: 'bg-green-100 text-green-700',
        denied: 'bg-red-100 text-red-700',
        pending: 'bg-yellow-100 text-yellow-700',
    })[s] ?? 'bg-gray-100';

export default function LeaveRequests({ requests }: Props) {
    const columns: ColumnDef<any>[] = [
        {
            accessorKey: 'employee',
            header: 'Employee',
            cell: ({ row }: CellContext<any, any>) => (
                <span>
                    {row.original.employee?.last_name},{' '}
                    {row.original.employee?.first_name}
                </span>
            ),
        },
        {
            accessorKey: 'date',
            header: 'Date',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="font-mono text-sm">{row.original.date}</span>
            ),
        },
        {
            accessorKey: 'leave_type',
            header: 'Type',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-xs capitalize">
                    {row.original.leave_type}
                </span>
            ),
        },
        {
            accessorKey: 'duration',
            header: 'Duration',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-xs capitalize">
                    {row.original.duration?.replace(/_/g, ' ')}
                </span>
            ),
        },
        {
            accessorKey: 'is_paid',
            header: 'Paid',
            cell: ({ row }: CellContext<any, any>) => (
                <span>{row.original.is_paid ? 'Yes' : 'No'}</span>
            ),
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }: CellContext<any, any>) => (
                <span
                    className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${statusBadge(row.original.status)}`}
                >
                    {row.original.status}
                </span>
            ),
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<any, any>) =>
                row.original.status === 'pending' ? (
                    <div className="flex gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    `/payroll/leave-requests/${row.original.id}/approve`,
                                    {},
                                    {
                                        onSuccess: () =>
                                            toast.success('Approved'),
                                    },
                                )
                            }
                        >
                            <Check className="h-4 w-4 text-green-600" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    `/payroll/leave-requests/${row.original.id}/deny`,
                                    {},
                                    {
                                        onSuccess: () =>
                                            toast.success('Denied'),
                                    },
                                )
                            }
                        >
                            <X className="h-4 w-4 text-red-500" />
                        </Button>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
        },
    ];

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave Requests" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Leave Requests</h1>
                </div>
                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={requests} />
                </div>
            </div>
        </PayrollLayout>
    );
}
