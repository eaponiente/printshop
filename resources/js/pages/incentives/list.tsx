import { Head, router, usePage } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { BadgeDollarSign, Calendar, XCircle } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import { DataTable } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Incentive, IncentivesList } from '@/types/incentives';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Incentives', href: '/incentives' },
];

const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

const statusConfig: Record<string, { label: string; className: string }> = {
    uncomputed: {
        label: 'Not Computed',
        className: 'bg-gray-100 text-gray-600 border-gray-200',
    },
    pending: {
        label: 'Pending',
        className: 'bg-amber-100 text-amber-600 border-amber-200',
    },
    paid: {
        label: 'Paid',
        className: 'bg-emerald-100 text-emerald-600 border-emerald-200',
    },
    cancelled: {
        label: 'Cancelled',
        className: 'bg-red-100 text-red-600 border-red-200',
    },
};

export default function IncentiveIndex({
    incentives,
    branches,
    history,
    filters,
}: IncentivesList) {
    const { auth } = usePage().props;
    const isSuperAdmin = auth.user.role === 'superadmin';

    const [payDialog, setPayDialog] = useState<Incentive | null>(null);
    const [incentiveInput, setIncentiveInput] = useState('');

    const handleMonthChange = useCallback(
        (value: string) => {
            router.get(
                route('incentives.index'),
                { ...filters, month: parseInt(value) },
                { preserveState: true, replace: true },
            );
        },
        [filters],
    );

    const handleYearChange = useCallback(
        (value: string) => {
            router.get(
                route('incentives.index'),
                { ...filters, year: parseInt(value) },
                { preserveState: true, replace: true },
            );
        },
        [filters],
    );

    const handleBranchChange = useCallback(
        (value: string) => {
            router.get(
                route('incentives.index'),
                { ...filters, branch_id: value },
                { preserveState: true, replace: true },
            );
        },
        [filters],
    );

    const handlePay = useCallback((incentive: Incentive) => {
        const suggested = Math.round(incentive.net_income * 0.05 * 100) / 100;
        setIncentiveInput(suggested > 0 ? String(suggested) : '0');
        setPayDialog(incentive);
    }, []);

    const confirmPay = useCallback(() => {
        if (!payDialog) {
            return;
        }

        router.post(
            route('incentives.pay'),
            {
                branch_id: payDialog.branch_id,
                user_id: payDialog.manager_id,
                month: payDialog.month,
                year: payDialog.year,
                incentive_amount: parseFloat(incentiveInput),
            },
            {
                onSuccess: () => {
                    setPayDialog(null);
                    toast.success('Incentive paid successfully.', {
                        position: 'top-center',
                    });
                },
                onError: (errors) =>
                    toast.error(errors.message, {
                        position: 'top-center',
                    }),
            },
        );
    }, [payDialog, incentiveInput]);

    const totalIncentive = useMemo(
        () => incentives.reduce((sum, i) => sum + i.incentive_amount, 0),
        [incentives],
    );

    const columns: ColumnDef<unknown, any>[] = useMemo(
        () => [
            {
                accessorKey: 'branch_name',
                header: 'Branch',
            },
            {
                accessorKey: 'incentive_amount',
                header: 'Incentive',
                cell: ({ row }: CellContext<any, any>) => (
                    <span className="font-semibold text-indigo-600">
                        {formatCurrency(row.original.incentive_amount)}
                    </span>
                ),
            },
            {
                accessorKey: 'cash_on_hand',
                header: 'Cash on Hand',
                cell: ({ row }: CellContext<any, any>) => (
                    <span
                        className={
                            row.original.cash_on_hand < 0 ? 'text-red-600' : ''
                        }
                    >
                        {formatCurrency(row.original.cash_on_hand)}
                    </span>
                ),
            },
            ...(isSuperAdmin
                ? [
                      {
                          accessorKey: 'owner_contribution',
                          header: 'Owner Pays',
                          cell: ({ row }: CellContext<any, any>) => {
                              const amount = row.original.owner_contribution;

                              if (!amount || amount <= 0) {
                                  return (
                                      <span className="text-xs text-muted-foreground">
                                          --
                                      </span>
                                  );
                              }

                              return (
                                  <span className="font-medium text-amber-600">
                                      {formatCurrency(amount)}
                                  </span>
                              );
                          },
                      },
                  ]
                : []),
            {
                accessorKey: 'manager_name',
                header: 'Manager',
                cell: ({ row }: CellContext<any, any>) => (
                    <span className="text-muted-foreground">
                        {row.original.manager_name}
                    </span>
                ),
            },
            {
                accessorKey: 'revenue',
                header: 'Revenue',
                cell: ({ row }: CellContext<any, any>) =>
                    formatCurrency(row.original.revenue),
            },
            {
                accessorKey: 'expenses',
                header: 'Expenses',
                cell: ({ row }: CellContext<any, any>) =>
                    formatCurrency(row.original.expenses),
            },
            {
                accessorKey: 'net_income',
                header: 'Net Income',
                cell: ({ row }: CellContext<any, any>) => (
                    <span
                        className={
                            row.original.net_income < 0
                                ? 'font-medium text-red-600'
                                : 'font-medium text-emerald-600'
                        }
                    >
                        {formatCurrency(row.original.net_income)}
                    </span>
                ),
            },
            {
                accessorKey: 'incentive_amount',
                header: 'Incentive',
                cell: ({ row }: CellContext<any, any>) => (
                    <span className="font-semibold text-indigo-600">
                        {formatCurrency(row.original.incentive_amount)}
                    </span>
                ),
            },
            {
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }: CellContext<any, any>) => {
                    const status = row.original
                        .status as keyof typeof statusConfig;
                    const config = statusConfig[status] ?? {
                        label: status,
                        className: '',
                    };

                    return (
                        <Badge
                            variant="outline"
                            className={`font-semibold capitalize ${config.className}`}
                        >
                            {config.label}
                        </Badge>
                    );
                },
            },
            ...(isSuperAdmin
                ? [
                      {
                          header: 'Action',
                          cell: ({ row }: CellContext<any, any>) => {
                              const incentive = row.original as Incentive;

                              if (incentive.status === 'paid') {
                                  return null;
                              }

                              return (
                                  <Button
                                      size="sm"
                                      variant="default"
                                      className="h-8 bg-indigo-600 text-white hover:bg-indigo-700"
                                      onClick={() => handlePay(incentive)}
                                  >
                                      <BadgeDollarSign className="mr-1.5 h-3.5 w-3.5" />
                                      Pay
                                  </Button>
                              );
                          },
                      },
                  ]
                : []),
        ],
        [isSuperAdmin, handlePay],
    );

    const historyColumns: ColumnDef<unknown, any>[] = useMemo(
        () => [
            {
                accessorKey: 'branch.name',
                header: 'Branch',
                cell: ({ row }: CellContext<any, any>) => (
                    <span className="font-medium">
                        {row.original.branch?.name}
                    </span>
                ),
            },
            {
                accessorKey: 'user',
                header: 'Recipient',
                cell: ({ row }: CellContext<any, any>) => (
                    <span className="text-muted-foreground">
                        {row.original.user?.first_name}{' '}
                        {row.original.user?.last_name}
                    </span>
                ),
            },
            {
                accessorKey: 'period',
                header: 'Period',
                cell: ({ row }: CellContext<any, any>) => (
                    <span>
                        {months[row.original.month - 1]} {row.original.year}
                    </span>
                ),
            },
            {
                accessorKey: 'net_income',
                header: 'Net Income',
                cell: ({ row }: CellContext<any, any>) =>
                    formatCurrency(row.original.net_income),
            },
            {
                accessorKey: 'incentive_amount',
                header: 'Incentive',
                cell: ({ row }: CellContext<any, any>) => (
                    <span className="font-semibold text-indigo-600">
                        {formatCurrency(row.original.incentive_amount)}
                    </span>
                ),
            },
            ...(isSuperAdmin
                ? [
                      {
                          accessorKey: 'owner_contribution',
                          header: 'Owner Paid',
                          cell: ({ row }: CellContext<any, any>) => {
                              const amount = row.original.owner_contribution;

                              if (!amount || amount <= 0) {
                                  return (
                                      <span className="text-xs text-muted-foreground">
                                          --
                                      </span>
                                  );
                              }

                              return (
                                  <span className="font-medium text-amber-600">
                                      {formatCurrency(amount)}
                                  </span>
                              );
                          },
                      },
                  ]
                : []),
            {
                accessorKey: 'paid_at',
                header: 'Paid At',
                cell: ({ row }: CellContext<any, any>) => {
                    const date = row.original.paid_at;

                    return date
                        ? new Date(date).toLocaleDateString('en-US', {
                              year: 'numeric',
                              month: 'short',
                              day: 'numeric',
                          })
                        : 'N/A';
                },
            },
        ],
        [],
    );

    const yearOptions = useMemo(() => {
        const currentYear = new Date().getFullYear();

        return Array.from({ length: 5 }, (_, i) => currentYear - i);
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Incentives" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Branch Incentives
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Monthly branch manager incentive payouts
                        </p>
                    </div>
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    <Card className="border-sidebar-border bg-sidebar">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="mb-1 text-xs font-semibold text-muted-foreground">
                                        Total Incentives Payable
                                    </p>
                                    <h2 className="text-2xl font-bold text-indigo-600">
                                        {formatCurrency(totalIncentive)}
                                    </h2>
                                </div>
                                <Calendar className="h-8 w-8 text-indigo-400" />
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {months[filters.month - 1]} {filters.year}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar p-4 lg:p-6">
                    <div className="mb-6 flex flex-wrap items-end gap-4">
                        {isSuperAdmin && (
                            <div className="flex flex-col gap-1.5">
                                <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                    Branch
                                </label>
                                <Select
                                    value={filters.branch_id || 'all'}
                                    onValueChange={handleBranchChange}
                                >
                                    <SelectTrigger className="h-10 w-45 bg-white text-sm shadow-sm">
                                        <SelectValue placeholder="All Branches" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Branches
                                        </SelectItem>
                                        {branches.map((branch) => (
                                            <SelectItem
                                                key={branch.id}
                                                value={String(branch.id)}
                                            >
                                                {branch.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <div className="flex flex-col gap-1.5">
                            <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                Month
                            </label>
                            <Select
                                value={String(filters.month)}
                                onValueChange={handleMonthChange}
                            >
                                <SelectTrigger className="h-10 w-40 bg-white text-sm shadow-sm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {months.map((name, i) => (
                                        <SelectItem
                                            key={i + 1}
                                            value={String(i + 1)}
                                        >
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                Year
                            </label>
                            <Select
                                value={String(filters.year)}
                                onValueChange={handleYearChange}
                            >
                                <SelectTrigger className="h-10 w-32 bg-white text-sm shadow-sm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {yearOptions.map((y) => (
                                        <SelectItem key={y} value={String(y)}>
                                            {y}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.get(
                                    route('incentives.index'),
                                    {},
                                    { replace: true },
                                )
                            }
                            className="h-10 px-3 text-muted-foreground transition-colors hover:text-destructive"
                        >
                            <XCircle className="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    </div>

                    <DataTable
                        columns={columns}
                        tableId="incentives-table"
                        pagination={{
                            data: incentives,
                            current_page: 1,
                            last_page: 1,
                            total: incentives.length,
                            prev_page_url: null,
                            next_page_url: null,
                        }}
                    />
                </div>

                {history.length > 0 && (
                    <div className="rounded-md border border-sidebar-border bg-sidebar p-4 lg:p-6">
                        <h3 className="mb-4 text-lg font-semibold">
                            Payment History
                        </h3>
                        <DataTable
                            columns={historyColumns}
                            tableId="incentives-history-table"
                            pagination={{
                                data: history,
                                current_page: 1,
                                last_page: 1,
                                total: history.length,
                                prev_page_url: null,
                                next_page_url: null,
                            }}
                        />
                    </div>
                )}
            </div>

            <Dialog
                open={payDialog !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPayDialog(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Pay Incentive</DialogTitle>
                    </DialogHeader>

                    {payDialog && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label className="text-xs text-muted-foreground">
                                        Branch
                                    </Label>
                                    <p className="font-medium">
                                        {payDialog.branch_name}
                                    </p>
                                </div>
                                <div>
                                    <Label className="text-xs text-muted-foreground">
                                        Manager
                                    </Label>
                                    <p className="font-medium">
                                        {payDialog.manager_name}
                                    </p>
                                </div>
                                <div>
                                    <Label className="text-xs text-muted-foreground">
                                        Net Income
                                    </Label>
                                    <p
                                        className={
                                            payDialog.net_income < 0
                                                ? 'font-medium text-red-600'
                                                : 'font-medium text-emerald-600'
                                        }
                                    >
                                        {formatCurrency(payDialog.net_income)}
                                    </p>
                                </div>
                                <div>
                                    <Label className="text-xs text-muted-foreground">
                                        Period
                                    </Label>
                                    <p className="font-medium">
                                        {months[payDialog.month - 1]}{' '}
                                        {payDialog.year}
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="incentive_amount">
                                    Incentive Amount
                                </Label>
                                <Input
                                    id="incentive_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={incentiveInput}
                                    onChange={(e) =>
                                        setIncentiveInput(e.target.value)
                                    }
                                    placeholder="0.00"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Suggested (5%):{' '}
                                    {formatCurrency(
                                        Math.round(
                                            payDialog.net_income * 0.05 * 100,
                                        ) / 100,
                                    )}
                                </p>
                            </div>

                            {incentiveInput &&
                                parseFloat(incentiveInput) > 0 && (
                                    <div className="space-y-2 rounded-md border bg-slate-50 p-3">
                                        <h4 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            Payment Breakdown
                                        </h4>

                                        <div className="flex justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Cash on Hand
                                            </span>
                                            <span
                                                className={
                                                    payDialog.cash_on_hand < 0
                                                        ? 'font-medium text-red-600'
                                                        : 'font-medium'
                                                }
                                            >
                                                {formatCurrency(
                                                    payDialog.cash_on_hand,
                                                )}
                                            </span>
                                        </div>

                                        <div className="flex justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                From Cash on Hand
                                            </span>
                                            <span className="font-medium text-emerald-600">
                                                {formatCurrency(
                                                    Math.min(
                                                        parseFloat(
                                                            incentiveInput,
                                                        ),
                                                        Math.max(
                                                            0,
                                                            payDialog.cash_on_hand,
                                                        ),
                                                    ),
                                                )}
                                            </span>
                                        </div>

                                        {parseFloat(incentiveInput) >
                                            Math.max(
                                                0,
                                                payDialog.cash_on_hand,
                                            ) && (
                                            <div className="flex justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    Owner Contributes
                                                </span>
                                                <span className="font-medium text-amber-600">
                                                    {formatCurrency(
                                                        parseFloat(
                                                            incentiveInput,
                                                        ) -
                                                            Math.max(
                                                                0,
                                                                payDialog.cash_on_hand,
                                                            ),
                                                    )}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                )}
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setPayDialog(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            className="bg-indigo-600 hover:bg-indigo-700"
                            onClick={confirmPay}
                            disabled={
                                !incentiveInput ||
                                parseFloat(incentiveInput) < 0
                            }
                        >
                            Pay
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
