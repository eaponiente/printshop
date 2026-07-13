import { Head, router, usePage } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDown,
    CreditCard,
    Pencil,
    Plus,
    RotateCcw,
    Trash2,
    Eye,
    ChevronDown,
    ChevronUp,
    BarChart3,
    Paperclip,
    Receipt,
    AlertCircle,
    CircleDashed,
    ExternalLink,
    Printer,
} from 'lucide-react';
import { useState, useCallback, useMemo, Suspense, lazy } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import AppLayout from '@/layouts/app-layout';
import TableFilters from '@/pages/sales/components/table-filters';

const SaleDialog = lazy(() => import('@/pages/sales/sales-dialog'));
const CollectPaymentDialog = lazy(
    () => import('@/pages/sales/components/collect-payment-dialog'),
);
const RefundPaymentDialog = lazy(
    () => import('@/pages/sales/components/refund-payment-dialog'),
);
const TransactionDetailsDialog = lazy(
    () => import('@/pages/sales/components/transaction-details-dialog'),
);
const SaleAttachmentDialog = lazy(
    () => import('@/pages/sales/components/sale-attachment-dialog'),
);

const statusConfig = {
    paid: 'bg-green-100 text-green-700 border-green-200',
    pending: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    partial: 'bg-blue-100 text-blue-700 border-blue-200',
};
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import type { TypeOfPayment } from '@/types/settings';
import type { Payment, Transaction } from '@/types/transaction';
import type { User } from '@/types/user';
import { toManilaTime } from '@/utils/dateHelper';
import { formatCurrency, getCustomerDisplayName } from '@/utils/formatters';
import { sortBy } from '@/utils/helpers';
import { printAllTableData } from '@/utils/printTable';
import SaleSummarySection from './components/sale-summary-section';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Projects', href: '/sales' },
];

interface SaleIndexProps {
    transactions: PaginatedResponse<Transaction | Payment>;
    filters: any;
    branches: any[];
    users: User[];
    types_of_payment: TypeOfPayment[];
    total_sales: number;
    net_income: number;
    cash_amount: number;
    cash_net_amount: number;
    gcash_amount: number;
    gcash_net_amount: number;
    check_amount: number;
    bank_transfer_amount: number;
    card_amount: number;
    debit_amount: number;
    cash_on_hand_amount: number;
    total_expenses: number;
    is_payment_view: boolean;
    show_summary: boolean;
}

/** Extract the parent Transaction from a row, regardless of payment or transaction view */
function getTx(row: any): Transaction {
    return row.transaction ?? row;
}

export default function SaleIndex({
    transactions,
    filters,
    branches,
    users = [],
    types_of_payment,
    total_sales = 0,
    net_income = 0,
    cash_amount = 0,
    cash_net_amount = 0,
    gcash_amount = 0,
    gcash_net_amount = 0,
    check_amount = 0,
    bank_transfer_amount = 0,
    card_amount = 0,
    debit_amount = 0,
    cash_on_hand_amount = 0,
    total_expenses = 0,
    is_payment_view = false,
    show_summary = false,
}: SaleIndexProps) {
    const [getTransaction, setTransaction] = useState<Transaction | null>(null);
    const { auth } = usePage<{
        auth: {
            user: User;
        };
    }>().props;

    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isSalesSummaryOpen, setIsSalesSummaryOpen] = useState(false);
    const [isDetailsDialogOpen, setIsDetailsDialogOpen] = useState(false);
    const [isCollectPaymentDialogOpen, setIsCollectPaymentDialogOpen] =
        useState(false);
    const [isRefundDialogOpen, setIsRefundDialogOpen] = useState(false);
    const [attachmentSaleId, setAttachmentSaleId] = useState<number | null>(
        null,
    );
    const [isAttachmentDialogOpen, setIsAttachmentDialogOpen] = useState(false);

    const attachmentTransaction = useMemo(
        () =>
            attachmentSaleId == null
                ? null
                : (() => {
                      const entry = transactions.data.find((t) => {
                          const tx = getTx(t);

                          return tx.id === attachmentSaleId;
                      });

                      return entry ? getTx(entry) : null;
                  })(),
        [transactions.data, attachmentSaleId],
    );

    const openEditForm = useCallback((transaction: Transaction | null) => {
        setTransaction(transaction);
        setIsDialogOpen(true);
    }, []);

    const deleteSale = useCallback((transaction: Transaction) => {
        router.delete(route('sales.destroy', transaction.id), {
            onSuccess: () =>
                toast.success(
                    transaction.invoice_number + ' has been deleted.',
                    { position: 'top-center' },
                ),
            onError: (errors) =>
                toast.error(errors.message, { position: 'top-center' }),
        });
    }, []);

    const openDetailsForm = useCallback((transaction: Transaction) => {
        setTransaction(transaction);
        setIsDetailsDialogOpen(true);
    }, []);

    const openAttachmentDialog = useCallback((transaction: Transaction) => {
        setAttachmentSaleId(transaction.id);
        setIsAttachmentDialogOpen(true);
    }, []);

    const setAttachmentDialogOpen = useCallback((open: boolean) => {
        setIsAttachmentDialogOpen(open);

        if (!open) {
            setAttachmentSaleId(null);
        }
    }, []);

    const selectedBranch = useMemo(
        () => branches.find((b) => b.id === Number(filters.branch_id)) || null,
        [branches, filters.branch_id],
    );

    const [mode, setMode] = useState(filters.mode || 'daily');
    const [activeTab, setActiveTab] = useState(filters.tab || 'partial');

    const handleTabChange = useCallback(
        (tab: string) => {
            setActiveTab(tab);
            router.get(
                '/sales',
                { ...filters, tab, page: 1 },
                { preserveState: true, replace: true },
            );
        },
        [filters],
    );

    const handleFilterChange = (
        value: string,
        type:
            | 'mode'
            | 'date'
            | 'branch_id'
            | 'staff_id'
            | 'payment_type'
            | 'search',
    ) => {
        const params = { ...filters, tab: activeTab };

        if (type === 'search') {
            params.search = value;
        } else if (type === 'mode') {
            setMode(value);
            params.mode = value;
            params.date = '';
        } else if (type === 'payment_type') {
            params.payment_type = value;
        } else if (type === 'branch_id') {
            params.branch_id = value;
            params.staff_id = '';
        } else if (type === 'staff_id') {
            params.staff_id = value;
        } else {
            params.date = value;
        }

        router.get(route('sales.index'), params, {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = useCallback(() => {
        setMode('daily');
        router.get(route('sales.index'), {}, { replace: true });
    }, []);

    const handleReceivePayment = useCallback((transaction: Transaction) => {
        setIsCollectPaymentDialogOpen(true);
        setTransaction(transaction);
    }, []);

    const handleRefundPayment = useCallback((transaction: Transaction) => {
        setIsRefundDialogOpen(true);
        setTransaction(transaction);
    }, []);

    const columns: ColumnDef<unknown, any>[] = useMemo(
        () => [
            {
                accessorKey: 'customer',
                header: 'Customer Name',
                cell: ({ row }: CellContext<any, any>) => {
                    const tx = getTx(row.original);
                    const name = getCustomerDisplayName(tx.customer);
                    const isLink = !!tx.sublimation;

                    return (
                        <div
                            className={`flex max-w-[150px] items-center gap-1 ${
                                isLink ? 'font-medium text-indigo-600' : ''
                            }`}
                            title={name}
                        >
                            <span className="truncate">{name}</span>
                            {isLink && (
                                <ExternalLink className="h-3.5 w-3.5 shrink-0 opacity-70" />
                            )}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'particular',
                header: 'Particular',
                cell: ({ row }: CellContext<any, any>) => {
                    const tx = getTx(row.original);

                    return (
                        <div
                            className="max-w-[110px] truncate"
                            title={tx.particular}
                        >
                            {tx.particular}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'branch',
                header: 'Branch',
                cell: ({ row }: CellContext<any, any>) => {
                    const tx = getTx(row.original);
                    const branchName = tx.branch?.name;

                    return (
                        <div
                            className="max-w-[150px] truncate"
                            title={branchName}
                        >
                            {branchName}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'amount_total',
                header: 'Total',
                cell: ({ row }: CellContext<any, any>) => {
                    const tx = getTx(row.original);

                    return formatCurrency(tx.amount_total);
                },
            },
            ...(is_payment_view
                ? [
                      {
                          accessorKey: 'amount',
                          header: 'Payment',
                          cell: ({ row }: any) =>
                              formatCurrency(row.original.amount),
                      },
                      {
                          accessorKey: 'payment_type',
                          header: 'Type',
                          cell: ({ row }: any) => (
                              <Badge className="border-slate-200 bg-slate-100 text-slate-700 capitalize shadow-none">
                                  {row.original.payment_type}
                              </Badge>
                          ),
                      },
                  ]
                : []),
            {
                accessorKey: 'balance',
                header: 'Balance',
                cell: ({ row }: CellContext<any, any>) => {
                    const tx = getTx(row.original);

                    return formatCurrency(tx.balance);
                },
            },
            {
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }: any) => {
                    const tx = getTx(row.original);
                    const status = tx.status.toLowerCase();

                    const badgeStyle =
                        statusConfig[status as keyof typeof statusConfig] ||
                        'bg-gray-100 text-gray-700';

                    return (
                        <Badge
                            className={`border font-medium capitalize shadow-none ${badgeStyle}`}
                        >
                            {status}
                        </Badge>
                    );
                },
            },
            {
                accessorKey: 'staff',
                header: 'Staff',
                cell: ({ row }: CellContext<any, any>) => {
                    const tx = getTx(row.original);
                    const staff = is_payment_view
                        ? row.original?.transaction?.user
                        : tx.user;
                    const staffName = staff
                        ? `${staff.first_name} ${staff.last_name}`
                        : (getTx(row.original).user?.fullname ?? '');

                    return (
                        <div
                            className="max-w-[120px] truncate"
                            title={staffName}
                        >
                            {staffName}
                        </div>
                    );
                },
            },
            {
                accessorKey: is_payment_view
                    ? 'created_at'
                    : 'transaction_date',
                cell: ({ row }: any) => {
                    const dateSource = is_payment_view
                        ? row.original.created_at
                        : row.original.transaction_date;

                    return toManilaTime(dateSource, 'MMM DD, YYYY');
                },
                header: () => {
                    const sortField = is_payment_view
                        ? 'created_at'
                        : 'transaction_date';
                    const isSorted = filters.sort_field === sortField;

                    return (
                        <Button
                            variant="ghost"
                            onClick={() =>
                                sortBy(sortField, filters, 'sales.index')
                            }
                            className="p-0 hover:bg-transparent"
                        >
                            Date
                            <ArrowUpDown
                                className={`ml-2 h-4 w-4 ${isSorted ? 'text-primary' : 'text-muted-foreground/50'}`}
                            />
                        </Button>
                    );
                },
            },
            ...(auth.user.role !== 'staff'
                ? [
                      {
                          id: 'payment',
                          header: 'Collection',
                          cell: ({ row }: any) => {
                              const tx = getTx(row.original);
                              const status = tx.status.toLowerCase();

                              return (
                                  <div className="flex gap-2 whitespace-nowrap">
                                      {status !== 'paid' && (
                                          <Button
                                              size="sm"
                                              variant="default"
                                              className="h-8 bg-indigo-600 text-white shadow-sm hover:bg-indigo-700"
                                              onClick={() =>
                                                  handleReceivePayment(tx)
                                              }
                                          >
                                              <CreditCard className="mr-2 h-3.5 w-3.5" />
                                              Collect
                                          </Button>
                                      )}
                                      {(status === 'partial' ||
                                          status === 'paid') &&
                                          (auth.user.role === 'superadmin' ||
                                              auth.user.role === 'admin') && (
                                              <Button
                                                  size="sm"
                                                  variant="outline"
                                                  className="h-8 border-amber-300 text-amber-700 hover:bg-amber-50"
                                                  onClick={() =>
                                                      handleRefundPayment(tx)
                                                  }
                                              >
                                                  <RotateCcw className="mr-2 h-3.5 w-3.5" />
                                                  Refund
                                              </Button>
                                          )}
                                  </div>
                              );
                          },
                      },
                  ]
                : []),
            {
                header: 'Actions',
                cell: ({ row }: CellContext<any, any>) => {
                    const tx = getTx(row.original);

                    return (
                        <div className="flex items-center gap-0.5 whitespace-nowrap">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => openDetailsForm(tx)}
                            >
                                <Eye className="h-4 w-4 text-muted-foreground" />
                            </Button>
                            {auth.user.role !== 'staff' && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    title="Upload attachment"
                                    onClick={() => openAttachmentDialog(tx)}
                                >
                                    <Paperclip className="h-4 w-4 text-muted-foreground" />
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => openEditForm(tx)}
                            >
                                <Pencil />
                            </Button>
                            {auth.user.role === 'superadmin' && (
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button variant="ghost" size="sm">
                                            <Trash2 />
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                Are you absolutely sure?
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                This action cannot be undone.
                                                This will permanently delete
                                                this sublimation.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>
                                                Cancel
                                            </AlertDialogCancel>
                                            <AlertDialogAction
                                                onClick={() => deleteSale(tx)}
                                            >
                                                Continue
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            )}
                        </div>
                    );
                },
            },
        ],
        [
            auth.user.role,
            is_payment_view,
            filters,
            handleReceivePayment,
            handleRefundPayment,
            openAttachmentDialog,
            openDetailsForm,
            openEditForm,
            deleteSale,
        ],
    );

    // Make the cells from Customer Name up to Date link to the related
    // sublimation (new tab), only for sublimation transactions. The Collection
    // and Actions columns stay interactive (not linked).
    const linkedColumns = useMemo(() => {
        const wrap =
            (render: (ctx: CellContext<any, any>) => any) =>
            (ctx: CellContext<any, any>) => {
                const tx = getTx(ctx.row.original);
                const content = render(ctx);

                if (!tx.sublimation) {
                    return content;
                }

                return (
                    <a
                        href={route('sublimations.index', {
                            id: tx.sublimation.id,
                        })}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="block cursor-pointer hover:text-indigo-600 hover:underline"
                        title="View sublimation"
                    >
                        {content}
                    </a>
                );
            };

        return columns.map((col) => {
            const id = (col as { id?: string }).id;
            const header = (col as { header?: unknown }).header;
            const cell = (col as { cell?: unknown }).cell;

            // Leave the Collection and Actions columns interactive.
            if (id === 'payment' || header === 'Actions' || typeof cell !== 'function') {
                return col;
            }

            return { ...col, cell: wrap(cell as (ctx: CellContext<any, any>) => any) };
        });
    }, [columns]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Projects</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your projects.
                        </p>
                    </div>

                    {/* Create Staff Button */}
                    <Button onClick={() => openEditForm(null)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Project
                    </Button>
                </div>

                {['superadmin', 'admin'].includes(auth.user.role) &&
                    show_summary && (
                        <div className="w-full">
                            <Collapsible
                                open={isSalesSummaryOpen}
                                onOpenChange={setIsSalesSummaryOpen}
                                className="rounded-lg border bg-white shadow-sm"
                            >
                                <div className="flex items-center justify-between px-4 py-3">
                                    <div className="flex items-center gap-2">
                                        <BarChart3 className="h-5 w-5 text-muted-foreground" />
                                        <h4 className="text-sm font-semibold">
                                            Projects Summary Overview
                                        </h4>
                                    </div>
                                    <CollapsibleTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="w-9 p-0"
                                        >
                                            {isSalesSummaryOpen ? (
                                                <ChevronUp className="h-4 w-4" />
                                            ) : (
                                                <ChevronDown className="h-4 w-4" />
                                            )}
                                            <span className="sr-only">
                                                Toggle Sales Summary
                                            </span>
                                        </Button>
                                    </CollapsibleTrigger>
                                </div>

                                <CollapsibleContent className="space-y-2 border-t">
                                    <div className="bg-slate-50/50 p-4">
                                        <SaleSummarySection
                                            total_sales={total_sales}
                                            net_income={net_income}
                                            cash_amount={cash_amount}
                                            cash_net_amount={cash_net_amount}
                                            gcash_amount={gcash_amount}
                                            gcash_net_amount={gcash_net_amount}
                                            check_amount={check_amount}
                                            bank_transfer_amount={
                                                bank_transfer_amount
                                            }
                                            card_amount={card_amount}
                                            cash_on_hand_amount={
                                                cash_on_hand_amount
                                            }
                                            debit_amount={debit_amount}
                                            total_expenses={total_expenses}
                                            selectedBranch={selectedBranch}
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </div>
                    )}

                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <TableFilters
                        mode={mode}
                        is_payment_view={is_payment_view}
                        filters={filters}
                        handleFilterChange={handleFilterChange}
                        clearFilters={clearFilters}
                        branches={branches}
                        types_of_payment={types_of_payment}
                        users={users}
                    />

                    <div className="flex gap-1 px-4 pb-2">
                        <Button
                            variant={
                                activeTab === 'partial' ? 'default' : 'ghost'
                            }
                            size="sm"
                            onClick={() => handleTabChange('partial')}
                            className={
                                activeTab === 'partial'
                                    ? 'bg-blue-500 hover:bg-blue-600'
                                    : ''
                            }
                        >
                            <CircleDashed className="mr-1.5 h-3.5 w-3.5" />
                            Partial
                        </Button>
                        <Button
                            variant={activeTab === 'paid' ? 'default' : 'ghost'}
                            size="sm"
                            onClick={() => handleTabChange('paid')}
                            className={
                                activeTab === 'paid'
                                    ? 'bg-green-600 hover:bg-green-700'
                                    : ''
                            }
                        >
                            <Receipt className="mr-1.5 h-3.5 w-3.5" />
                            Paid
                        </Button>
                        <Button
                            variant={
                                activeTab === 'unpaid' ? 'default' : 'ghost'
                            }
                            size="sm"
                            onClick={() => handleTabChange('unpaid')}
                            className={
                                activeTab === 'unpaid'
                                    ? 'bg-amber-500 hover:bg-amber-600'
                                    : ''
                            }
                        >
                            <AlertCircle className="mr-1.5 h-3.5 w-3.5" />
                            Unpaid
                        </Button>
                        {show_summary &&
                            auth.user.role === 'superadmin' && (
                                <div className="ml-auto">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            printAllTableData(
                                                'Sales Report',
                                                route('sales.print', {
                                                    ...filters,
                                                    tab: activeTab,
                                                }),
                                            )
                                        }
                                    >
                                        <Printer className="mr-1.5 h-3.5 w-3.5" />
                                        Print
                                    </Button>
                                </div>
                            )}
                    </div>

                    <DataTable
                        columns={linkedColumns}
                        tableId="sales-table"
                        pagination={transactions}
                    />
                </div>
            </div>

            <Suspense fallback={null}>
                {isDialogOpen && (
                    <SaleDialog
                        open={isDialogOpen}
                        setOpen={setIsDialogOpen}
                        branches={branches}
                        transaction={getTransaction}
                    />
                )}

                {isCollectPaymentDialogOpen && (
                    <CollectPaymentDialog
                        transaction={getTransaction}
                        open={isCollectPaymentDialogOpen}
                        typesOfPayment={types_of_payment}
                        setOpen={setIsCollectPaymentDialogOpen}
                    />
                )}

                {isRefundDialogOpen && (
                    <RefundPaymentDialog
                        transaction={getTransaction}
                        open={isRefundDialogOpen}
                        setOpen={setIsRefundDialogOpen}
                    />
                )}

                {isDetailsDialogOpen && (
                    <TransactionDetailsDialog
                        transaction={getTransaction}
                        open={isDetailsDialogOpen}
                        setOpen={setIsDetailsDialogOpen}
                    />
                )}

                {isAttachmentDialogOpen && attachmentTransaction && (
                    <SaleAttachmentDialog
                        transaction={attachmentTransaction}
                        open={isAttachmentDialogOpen}
                        setOpen={setAttachmentDialogOpen}
                    />
                )}
            </Suspense>
        </AppLayout>
    );
}
