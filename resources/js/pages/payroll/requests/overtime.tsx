import { Head, router, usePage } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Check, Plus, X } from 'lucide-react';
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
import OvertimeRequestForm from './components/OvertimeRequestForm';

type Props = { requests: PaginatedResponse<any> };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Overtime Requests', href: '/payroll/overtime-requests' },
];

const statusBadge = (s: string) =>
    ({
        approved: 'bg-green-100 text-green-700',
        denied: 'bg-red-100 text-red-700',
        pending: 'bg-yellow-100 text-yellow-700',
    })[s] ?? 'bg-gray-100';

export default function OvertimeRequests({ requests }: Props) {
    const { auth } = usePage().props as any;
    const canApprove =
        auth?.user?.role === 'admin' || auth?.user?.role === 'superadmin';
    const [dialogOpen, setDialogOpen] = useState(false);

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
            accessorKey: 'start_time',
            header: 'Start',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="font-mono text-sm">
                    {row.original.start_time
                        ? new Date(row.original.start_time).toLocaleTimeString(
                              'en-PH',
                              {
                                  hour: '2-digit',
                                  minute: '2-digit',
                                  hour12: false,
                              },
                          )
                        : '—'}
                </span>
            ),
        },
        {
            accessorKey: 'end_time',
            header: 'End',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="font-mono text-sm">
                    {row.original.end_time
                        ? new Date(row.original.end_time).toLocaleTimeString(
                              'en-PH',
                              {
                                  hour: '2-digit',
                                  minute: '2-digit',
                                  hour12: false,
                              },
                          )
                        : '—'}
                </span>
            ),
        },
        {
            accessorKey: 'shift_type',
            header: 'Shift',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-xs capitalize">
                    {row.original.shift_type?.replace(/_/g, ' ')}
                </span>
            ),
        },
        {
            accessorKey: 'reason',
            header: 'Reason',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-xs text-muted-foreground">
                    {row.original.reason}
                </span>
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
                                              `/payroll/overtime-requests/${row.original.id}/approve`,
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
                                              `/payroll/overtime-requests/${row.original.id}/deny`,
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
            <Head title="Overtime Requests" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Overtime Requests</h1>
                    <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                        <DialogTrigger asChild>
                            <Button size="sm">
                                <Plus className="mr-1 h-4 w-4" />
                                New Request
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Request Overtime</DialogTitle>
                            </DialogHeader>
                            <OvertimeRequestForm
                                onClose={() => setDialogOpen(false)}
                            />
                        </DialogContent>
                    </Dialog>
                </div>
                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={requests} />
                </div>
            </div>
        </PayrollLayout>
    );
}
