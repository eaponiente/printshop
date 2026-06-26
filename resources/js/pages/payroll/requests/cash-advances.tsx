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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { toManilaTime } from '@/utils/dateHelper';
import { formatCurrency } from '@/utils/formatters';
import CashAdvanceForm from './components/CashAdvanceForm';

type Props = { requests: PaginatedResponse<any> };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Cash Advances', href: '/payroll/cash-advances' },
];
const statusBadge = (s: string) =>
    ({
        approved: 'bg-green-100 text-green-700',
        paid: 'bg-blue-100 text-blue-700',
        denied: 'bg-red-100 text-red-700',
        pending: 'bg-yellow-100 text-yellow-700',
        unpaid: 'bg-orange-100 text-orange-700',
    })[s] ?? 'bg-gray-100';

export default function CashAdvances({ requests }: Props) {
    const { auth } = usePage().props as any;
    const isSuperadmin = auth?.user?.role === 'superadmin';
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
            accessorKey: 'amount',
            header: 'Amount',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="font-mono text-sm">
                    {formatCurrency(row.original.amount)}
                </span>
            ),
        },
        {
            accessorKey: 'remaining_balance',
            header: 'Balance',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="font-mono text-sm">
                    {formatCurrency(row.original.remaining_balance)}
                </span>
            ),
        },
        ...(isSuperadmin
            ? [
                  {
                      accessorKey: 'branch',
                      header: 'Branch',
                      cell: ({ row }: CellContext<any, any>) => (
                          <span className="text-xs text-muted-foreground">
                              {row.original.employee?.branch?.name ?? '—'}
                          </span>
                      ),
                  } as ColumnDef<any>,
              ]
            : []),
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
            accessorKey: 'created_at',
            header: 'Submitted',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-xs text-muted-foreground">
                    {toManilaTime(row.original.created_at)}
                </span>
            ),
        },
        {
            accessorKey: 'approved_by',
            header: 'Approved By',
            cell: ({ row }: CellContext<any, any>) => {
                const approver = row.original.approved_by;
                return (
                    <span className="text-xs text-muted-foreground">
                        {approver
                            ? `${approver.last_name}, ${approver.first_name}`
                            : '—'}
                    </span>
                );
            },
        },
        {
            accessorKey: 'approved_at',
            header: 'Approved At',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-xs text-muted-foreground">
                    {toManilaTime(row.original.approved_at)}
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
                                              `/payroll/cash-advances/${row.original.id}/approve`,
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
                                              `/payroll/cash-advances/${row.original.id}/deny`,
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
            <Head title="Cash Advances" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Cash Advances</h1>
                    <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                        <DialogTrigger asChild>
                            <Button size="sm">
                                <Plus className="mr-1 h-4 w-4" />
                                New Request
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Request Cash Advance</DialogTitle>
                            </DialogHeader>
                            <CashAdvanceForm
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
