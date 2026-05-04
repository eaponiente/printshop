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
} from 'lucide-react';
import { useState, useCallback, useMemo, Suspense, lazy } from 'react';
import { route } from 'ziggy-js';
import { DataTable } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import AppLayout from '@/layouts/app-layout';
import TableFilters from '@/pages/sales/components/table-filters';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/components/ui/collapsible";

const SaleDialog = lazy(() => import('@/pages/sales/sales-dialog'));
const CollectPaymentDialog = lazy(() => import('@/pages/sales/components/collect-payment-dialog'));
const RefundPaymentDialog = lazy(() => import('@/pages/sales/components/refund-payment-dialog'));
const TransactionDetailsDialog = lazy(() => import('@/pages/sales/components/transaction-details-dialog'));
const SaleAttachmentDialog = lazy(() => import('@/pages/sales/components/sale-attachment-dialog'));

const statusConfig = {
    paid: 'bg-green-100 text-green-700 border-green-200',
    pending: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    partial: 'bg-blue-100 text-blue-700 border-blue-200',
};
import type { BreadcrumbItem } from '@/types';
import type { Branch } from '@/types/branches';
import type { PaginatedResponse } from '@/types/pagination';
import type { TypeOfPayment } from '@/types/settings';
import type { Payment, Transaction } from '@/types/transaction';
import type { Customer, User } from '@/types/user';
import { toManilaTime } from '@/utils/dateHelper';
import { formatCurrency, getCustomerDisplayName } from '@/utils/formatters';
import { sortBy } from '@/utils/helpers';
import { toast } from 'sonner';
import SaleSummarySection from './components/sale-summary-section';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Projects', href: '/sales' },
];

interface SaleIndexProps {
    transactions: PaginatedResponse<Transaction | Payment>;
    filters: any;
    branches: any[];
    types_of_payment: TypeOfPayment[];
    total_sales: number;
    net_income: number;
    cash_amount: number;
    gcash_amount: number;
    check_amount: number;
    bank_transfer_amount: number;
    card_amount: number;
    debit_amount: number;
    cash_on_hand_amount: number;
    total_expenses: number;
    is_payment_view: boolean;
}

/** Extract the parent Transaction from a row, regardless of payment or transaction view */
function getTx(row: any): Transaction {
    return row.transaction ?? row;
}

export default function SaleIndex({
    transactions,
    filters,
    branches,
    types_of_payment,
    total_sales = 0,
    net_income = 0,
    cash_amount = 0,
    gcash_amount = 0,
    check_amount = 0,
    bank_transfer_amount = 0,
    card_amount = 0,
    debit_amount = 0,
    cash_on_hand_amount = 0,
    total_expenses = 0,
    is_payment_view = false,
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
    const [isAttachmentDialogOpen, setIsAttachmentDialogOpen] =
        useState(false);

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

    const deleteSale = (transaction: Transaction) => {
        router.delete(route('sales.destroy', transaction.id), {
            onSuccess: () => toast.success(transaction.invoice_number + ' has been deleted.', { position: 'top-center' }),
            onError: (errors) => toast.error(errors.message, { position: 'top-center' }),
        });
    };

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
    const [activeTab, setActiveTab] = useState(filters.tab || 'payments');

    const handleTabChange = useCallback((tab: string) => {
        setActiveTab(tab);
        router.get('/sales', { ...filters, tab, page: 1 }, { preserveState: true, replace: true });
    }, [filters]);

    const handleFilterChange = useCallback((
        value: string,
        type: 'mode' | 'date' | 'status' | 'branch_id' | 'payment_type' | 'search',
    ) => {
        const params = { ...filters, tab: activeTab };

        if (type === 'search') {
            params.search = value;
        } else if (type === 'mode') {
            setMode(value);
            params.mode = value;
            params.date = '';
        } else if (type === 'status') {
            params.status = value;
        } else if (type === 'payment_type') {
            params.payment_type = value;
        } else if (type === 'branch_id') {
            params.branch_id = value;
        } else {
            params.date = value;
        }

        if (type === 'search') {
            router.get(route('sales.index'), { ...params, page: 1 }, { preserveState: true, replace: true });
        } else {
            router.get(`/sales`, params, { preserveState: true, replace: true });
        }
    }, [filters, activeTab]);

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

    const columns: ColumnDef<unknown, any>[] = useMemo(() => [
        {
            accessorKey: 'customer',
            header: 'Customer Name',
            cell: ({ row }: CellContext<any, any>) => {
                const tx = getTx(row.original);
                const name = getCustomerDisplayName(tx.customer);
                return (
                    <div className="max-w-[120px] truncate" title={name}>
                        {name}
                    </div>
                );
            }
        },
        {
            accessorKey: 'particular',
            header: 'Particular',
            cell: ({ row }: CellContext<any, any>) => {
                const tx = getTx(row.original);
                return (
                    <div className="max-w-[110px] truncate" title={tx.particular}>
                        {tx.particular}
                    </div>
                );
            }
        },
        {
            accessorKey: 'branch',
            header: 'Branch',
            cell: ({ row }: CellContext<any, any>) => {
                const tx = getTx(row.original);
                const branchName = tx.branch?.name;
                return (
                    <div className="max-w-[150px] truncate" title={branchName}>
                        {branchName}
                    </div>
                );
            }
        },
        {
            accessorKey: 'amount_total',
            header: 'Total',
            cell: ({ row }: CellContext<any, any>) => {
                const tx = getTx(row.original);
                return formatCurrency(tx.amount_total);
            }
        },
        ...(is_payment_view ? [
            {
                accessorKey: 'amount',
                header: 'Payment',
                cell: ({ row }: any) => formatCurrency(row.original.amount),
            },
            {
                accessorKey: 'payment_type',
                header: 'Type',
                cell: ({ row }: any) => (
                    <Badge className="capitalize bg-slate-100 text-slate-700 border-slate-200 shadow-none">
                        {row.original.payment_type}
                    </Badge>
                ),
            },
        ] : []),
        {
            accessorKey: 'balance',
            header: 'Balance',
            cell: ({ row }: CellContext<any, any>) => {
                const tx = getTx(row.original);
                return formatCurrency(tx.balance);
            }
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
                const staff = is_payment_view ? row.original?.transaction?.user : tx.user;
                const staffName = staff
                    ? `${staff.first_name} ${staff.last_name}`
                    : getTx(row.original).user?.fullname ?? '';
                return (
                    <div className="max-w-[120px] truncate" title={staffName}>
                        {staffName}
                    </div>
                );
            }
        },
        {
            accessorKey: is_payment_view ? 'created_at' : 'transaction_date',
            cell: ({ row }: any) => {
                const dateSource = is_payment_view
                    ? row.original.created_at
                    : row.original.transaction_date;
                return toManilaTime(dateSource, 'MMM DD, YYYY');
            },
            header: () => {
                const sortField = is_payment_view ? 'created_at' : 'transaction_date';
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
        ...(auth.user.role !== 'staff' ? [{
            id: 'payment',
            header: 'Collection',
            cell: ({ row }: any) => {
                const tx = getTx(row.original);
                const status = tx.status.toLowerCase();
                if (status === 'paid') return null;

                return (
                    <div className="flex gap-2 whitespace-nowrap">
                        {status !== 'paid' && (
                            <Button
                                size="sm"
                                variant="default"
                                className="h-8 bg-indigo-600 text-white shadow-sm hover:bg-indigo-700"
                                onClick={() => handleReceivePayment(tx)}
                            >
                                <CreditCard className="mr-2 h-3.5 w-3.5" />
                                Collect
                            </Button>
                        )}
                        {status === 'partial' &&
                            auth.user.role === 'superadmin' && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="h-8 border-amber-300 text-amber-700 hover:bg-amber-50"
                                    onClick={() => handleRefundPayment(tx)}
                                >
                                    <RotateCcw className="mr-2 h-3.5 w-3.5" />
                                    Refund
                                </Button>
                            )}
                    </div>
                );
            },
        }] : []),
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
                                            This action cannot be undone. This will
                                            permanently delete this sublimation.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
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
    ], [auth.user.role, is_payment_view, filters, handleReceivePayment, handleRefundPayment, openAttachmentDialog, openDetailsForm, openEditForm, deleteSale]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Projects
                        </h1>
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

                {['superadmin', 'admin'].includes(auth.user.role) && (
                    <div className="w-full">
                        <Collapsible
                            open={isSalesSummaryOpen}
                            onOpenChange={setIsSalesSummaryOpen}
                            className="border rounded-lg bg-white shadow-sm"
                        >
                            <div className="flex items-center justify-between px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <BarChart3 className="h-5 w-5 text-muted-foreground" />
                                    <h4 className="text-sm font-semibold">
                                        Projects Summary Overview
                                    </h4>
                                </div>
                                <CollapsibleTrigger asChild>
                                    <Button variant="ghost" size="sm" className="w-9 p-0">
                                        {isSalesSummaryOpen ? (
                                            <ChevronUp className="h-4 w-4" />
                                        ) : (
                                            <ChevronDown className="h-4 w-4" />
                                        )}
                                        <span className="sr-only">Toggle Sales Summary</span>
                                    </Button>
                                </CollapsibleTrigger>
                            </div>

                            <CollapsibleContent className="space-y-2 border-t">
                                <div className="p-4 bg-slate-50/50">
                                    <SaleSummarySection
                                        total_sales={total_sales}
                                        net_income={net_income}
                                        cash_amount={cash_amount}
                                        gcash_amount={gcash_amount}
                                        check_amount={check_amount}
                                        bank_transfer_amount={bank_transfer_amount}
                                        card_amount={card_amount}
                                        cash_on_hand_amount={cash_on_hand_amount}
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
                        filters={filters}
                        handleFilterChange={handleFilterChange}
                        clearFilters={clearFilters}
                        branches={branches}
                    />

                    <div className="flex gap-1 px-4 pb-2">
                        <Button
                            variant={activeTab === 'payments' ? 'default' : 'ghost'}
                            size="sm"
                            onClick={() => handleTabChange('payments')}
                            className={activeTab === 'payments' ? 'bg-indigo-600 hover:bg-indigo-700' : ''}
                        >
                            <Receipt className="mr-1.5 h-3.5 w-3.5" />
                            Payments
                        </Button>
                        <Button
                            variant={activeTab === 'unpaid' ? 'default' : 'ghost'}
                            size="sm"
                            onClick={() => handleTabChange('unpaid')}
                            className={activeTab === 'unpaid' ? 'bg-amber-500 hover:bg-amber-600' : ''}
                        >
                            <AlertCircle className="mr-1.5 h-3.5 w-3.5" />
                            Unpaid
                        </Button>
                    </div>

                    <DataTable columns={columns} pagination={transactions} />
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
                        typesOfPayment={types_of_payment}
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
