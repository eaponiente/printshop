import { Head, router, usePage } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Check, Plus, RefreshCw, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import LeaveRequestForm from './components/LeaveRequestForm';

type Props = {
    requests: PaginatedResponse<any>;
    employeeSummary: {
        default_paid_leave_days: number;
        paid_leave_balance: number;
        used_paid_leave_days: number;
    } | null;
    canResetLeaves: boolean;
};

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

export default function LeaveRequests({
    requests,
    employeeSummary,
    canResetLeaves,
}: Props) {
    const { auth } = usePage().props as any;
    const canApprove =
        auth?.user?.role === 'admin' || auth?.user?.role === 'superadmin';
    const [dialogOpen, setDialogOpen] = useState(false);

    const handleReset = () => {
        if (
            !confirm(
                'Reset all active employee leave balances to their defaults?',
            )
        ) {
return;
}

        router.post(
            '/payroll/leave-requests/reset',
            {},
            {
                onSuccess: () => toast.success('Leave balances reset.'),
                onError: (err: any) =>
                    toast.error(err?.error || 'Failed to reset.'),
            },
        );
    };

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
            accessorKey: 'balance',
            header: 'Remaining',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="font-medium">
                    {row.original.employee?.paid_leave_balance ?? '—'}
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
        ...(canApprove
            ? [
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
                                                  onError: (err: any) => {
                                                      const msg =
                                                          err?.error ||
                                                          err?.message ||
                                                          'Failed to approve.';
                                                      toast.error(msg);
                                                  },
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
                                                  onError: (err: any) => {
                                                      const msg =
                                                          err?.error ||
                                                          err?.message ||
                                                          'Failed to deny.';
                                                      toast.error(msg);
                                                  },
                                              },
                                          )
                                      }
                                  >
                                      <X className="h-4 w-4 text-red-500" />
                                  </Button>
                              </div>
                          ) : (
                              <span className="text-xs text-muted-foreground">
                                  —
                              </span>
                          ),
                  } as ColumnDef<any>,
              ]
            : []),
    ];

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave Requests" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {employeeSummary && (
                    <div className="rounded-md border border-sidebar-border bg-sidebar p-4 text-sm">
                        <div className="flex items-center gap-6">
                            <div>
                                <span className="text-muted-foreground">
                                    Entitlement:{' '}
                                </span>
                                <span className="font-semibold">
                                    {employeeSummary.default_paid_leave_days}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    Used:{' '}
                                </span>
                                <span className="font-semibold text-red-600">
                                    {employeeSummary.used_paid_leave_days}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    Remaining:{' '}
                                </span>
                                <span className="font-semibold text-green-600">
                                    {employeeSummary.paid_leave_balance}
                                </span>
                            </div>
                        </div>
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Leave Requests</h1>
                    <div className="flex items-center gap-2">
                        {canResetLeaves && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleReset}
                            >
                                <RefreshCw className="mr-1 h-4 w-4" />
                                Reset All Leave
                            </Button>
                        )}
                        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                            <DialogTrigger asChild>
                                <Button size="sm">
                                    <Plus className="mr-1 h-4 w-4" />
                                    New Request
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Request Leave</DialogTitle>
                                </DialogHeader>
                                <LeaveRequestForm
                                    onClose={() => setDialogOpen(false)}
                                />
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={requests} />
                </div>
            </div>
        </PayrollLayout>
    );
}
