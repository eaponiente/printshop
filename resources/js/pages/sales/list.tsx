import { Head, router, usePage } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDown,
    CreditCard,
    Pencil,
    Plus,
    Trash2,
    Wallet,
    Eye,
} from 'lucide-react';
import { Banknote, TrendingUp } from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { DataTable } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card'; // Ensure you have these shadcn components
import AppLayout from '@/layouts/app-layout';
import CollectPaymentDialog from '@/pages/sales/components/collect-payment-dialog';
import TableFilters from '@/pages/sales/components/table-filters';
import TransactionDetailsDialog from '@/pages/sales/components/transaction-details-dialog';
import SaleDialog from '@/pages/sales/sales-dialog';
import type { BreadcrumbItem } from '@/types';
import type { Branch } from '@/types/branches';
import type { PaginatedResponse } from '@/types/pagination';
import type { TypeOfPayment } from '@/types/settings';
import type { Transaction } from '@/types/transaction';
import type { Customer, User } from '@/types/user';
import { toManilaTime } from '@/utils/dateHelper';
import { formatCurrency } from '@/utils/formatters';
import { sortBy } from '@/utils/helpers';
import { toast } from 'sonner';
import SaleSummarySection from './components/sale-summary-section';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales', href: '/sales' },
];

interface SaleIndexProps {
    transactions: PaginatedResponse<Transaction>; // Use the generic here
    filters: any;
    branches: any[];
    customers: Customer[];
    types_of_payment: TypeOfPayment[];
    total_sales: number;
    net_income: number;
    cash_amount: number;
    gcash_amount: number;
    check_amount: number;
    bank_transfer_amount: number;
    card_amount: number;
    cash_on_hand_amount: number;
    total_expenses: number;
}

export default function SaleIndex({
    transactions,
    filters,
    branches,
    customers,
    types_of_payment,
    total_sales = 0,
    net_income = 0,
    cash_amount = 0,
    gcash_amount = 0,
    check_amount = 0,
    bank_transfer_amount = 0,
    card_amount = 0,
    cash_on_hand_amount = 0,
    total_expenses = 0,
}: SaleIndexProps) {
    const [getTransaction, setTransaction] = useState<Transaction | null>(null);
    const { auth } = usePage<{
        auth: {
            user: User;
        };
    }>().props;

    const openEditForm = (transaction: Transaction | null) => {
        setTransaction(transaction);
        setIsDialogOpen(true);
    };

    const openDetailsForm = (transaction: Transaction) => {
        setTransaction(transaction);
        setIsDetailsDialogOpen(true);
    };

    // 1. Add local state for the search input
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedBranch, setSelectedBranch] = useState<Branch | null>(null);

    // 2. Debounce Search Logic: Updates the URL after user stops typing
    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (searchTerm !== (filters.search || '')) {
                router.get(
                    route('sales.index'),
                    { ...filters, search: searchTerm, page: 1 },
                    { preserveState: true, replace: true },
                );
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [filters, searchTerm]);

    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isDetailsDialogOpen, setIsDetailsDialogOpen] = useState(false);
    const [isCollectPaymentDialogOpen, setIsCollectPaymentDialogOpen] =
        useState(false);

    const [mode, setMode] = useState(filters.mode || 'daily');

    const handleFilterChange = (
        value: string,
        type: 'mode' | 'date' | 'status' | 'branch_id' | 'payment_type',
    ) => {
        const params = { ...filters, search: searchTerm };

        if (type === 'mode') {
            setMode(value);
            params.mode = value;
            // Reset date when switching modes to avoid invalid matches
            params.date = '';
        } else if (type === 'status') {
            params.status = value;
        } else if (type === 'payment_type') {
            params.payment_type = value;
        } else if (type === 'branch_id') {
            params.branch_id = value;

            setSelectedBranch(
                branches.find((b) => b.id === Number(params.branch_id)),
            );
        } else {
            params.date = value;
        }

        router.get(`/sales`, params, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        setMode('daily');

        router.get(route('sales.index'), {}, { replace: true });
    };

    const handleReceivePayment = (transaction: Transaction) => {
        setIsCollectPaymentDialogOpen(true);
        setTransaction(transaction);
    };

    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'invoice_number',
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() =>
                            column.toggleSorting(column.getIsSorted() === 'asc')
                        }
                    >
                        Invoice #
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            },
        },
        {
            accessorKey: 'customer.full_name',
            header: 'Customer Name',
        },
        {
            accessorKey: 'particular',
            header: 'Particular',
        },
        {
            accessorKey: 'branch.name',
            header: 'Branch',
        },
        {
            accessorKey: 'amount_total',
            header: 'Total',
        },
        {
            accessorKey: 'balance',
            header: 'Balance',
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }: any) => {
                const status = row.original.status.toLowerCase();
                const statusConfig = {
                    paid: 'bg-green-100 text-green-700 border-green-200',
                    pending: 'bg-yellow-100 text-yellow-700 border-yellow-200',
                    partial: 'bg-blue-100 text-blue-700 border-blue-200',
                };
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
            accessorKey: 'user.fullname',
            header: 'Staff',
        },
        {
            accessorKey: 'transaction_date',
            cell: ({ row }: any) => {
                return toManilaTime(
                    row.original.transaction_date,
                    'MMM DD, YYYY',
                );
            },
            header: () => {
                const isSorted = filters.sort_field === 'transaction_date';

                return (
                    <Button
                        variant="ghost"
                        // Pass the field, the current filters object, and the route
                        onClick={() =>
                            sortBy('transaction_date', filters, 'sales.index')
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
        {
            id: 'payment',
            header: 'Collection',
            cell: ({ row }: any) => {
                const status = row.original.status.toLowerCase();

                if (status === 'paid') {
                    return;
                }

                return (
                    <Button
                        size="sm"
                        variant="default"
                        className="h-8 bg-indigo-600 text-white shadow-sm hover:bg-indigo-700"
                        onClick={() => handleReceivePayment(row.original)}
                    >
                        <CreditCard className="mr-2 h-3.5 w-3.5" />
                        Collect Pay
                    </Button>
                );
            },
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<any, any>) => {
                return (
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => openDetailsForm(row.original)}
                        >
                            <Eye className="h-4 w-4 text-muted-foreground" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => openEditForm(row.original)}
                        >
                            <Pencil />
                        </Button>
                    </>
                );
            },
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Sale Management
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your sale.
                        </p>
                    </div>

                    {/* Create Staff Button */}
                    <Button onClick={() => openEditForm(null)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Transaction
                    </Button>
                </div>

                {['superadmin', 'admin'].includes(auth.user.role) && (
                    <SaleSummarySection
                        total_sales={total_sales}
                        net_income={net_income}
                        cash_amount={cash_amount}
                        gcash_amount={gcash_amount}
                        check_amount={check_amount}
                        bank_transfer_amount={bank_transfer_amount}
                        card_amount={card_amount}
                        cash_on_hand_amount={cash_on_hand_amount}
                        total_expenses={total_expenses}
                        selectedBranch={selectedBranch}
                    />
                )}

                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <TableFilters
                        searchTerm={searchTerm}
                        setSearchTerm={setSearchTerm}
                        mode={mode}
                        filters={filters}
                        handleFilterChange={handleFilterChange}
                        clearFilters={clearFilters}
                        branches={branches}
                    />

                    <DataTable columns={columns} pagination={transactions} />
                </div>
            </div>

            {isDialogOpen && (
                <SaleDialog
                    open={isDialogOpen}
                    setOpen={setIsDialogOpen}
                    branches={branches}
                    transaction={getTransaction}
                    customers={customers}
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

            {isDetailsDialogOpen && (
                <TransactionDetailsDialog
                    transaction={getTransaction}
                    open={isDetailsDialogOpen}
                    setOpen={setIsDetailsDialogOpen}
                />
            )}
        </AppLayout>
    );
}
