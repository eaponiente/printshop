import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, CreditCard, Pencil, Plus, Trash2 } from 'lucide-react';
import { Banknote, TrendingUp } from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { route } from 'ziggy-js';
import { DataTable } from '@/components/data-table';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel,
    AlertDialogContent, AlertDialogDescription, AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from "@/components/ui/card"; // Ensure you have these shadcn components
import AppLayout from '@/layouts/app-layout';
import TableFilters from '@/pages/sales/components/table-filters';
import SaleDialog from '@/pages/sales/sales-dialog';
import type { BreadcrumbItem } from '@/types';
import type { Branch } from '@/types/branches';
import type { PaginatedResponse } from '@/types/pagination';
import type { Transaction } from '@/types/transaction';
import type { Customer } from '@/types/user';
import { TypeOfPayment } from '@/types/settings';
import { formatCurrency } from '@/utils/formatters';
import { sortBy } from '@/utils/helpers';
import CollectPaymentDialog from '@/pages/sales/components/collect-payment-dialog';

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
    total_balance: number;
}

export default function SaleIndex({ transactions, filters, branches, customers, types_of_payment, total_sales = 0, total_balance = 0 }: SaleIndexProps) {

    const [getTransaction, setTransaction] = useState<any | null>(null);
    const openEditForm = (transaction: any) => {
        setTransaction(transaction);
        setIsDialogOpen(true);
    };

    // 1. Add local state for the search input
    const [searchTerm, setSearchTerm] = useState(filters.search || "");
    const [selectedBranch, setSelectedBranch] = useState<Branch | null>(null);

    // 2. Debounce Search Logic: Updates the URL after user stops typing
    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (searchTerm !== (filters.search || "")) {
                router.get(route('sales.index'),
                    { ...filters, search: searchTerm, page: 1 },
                    { preserveState: true, replace: true }
                );
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [searchTerm]);

    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isCollectPaymentDialogOpen, setIsCollectPaymentDialogOpen] = useState(false);

    const [mode, setMode] = useState(filters.mode || "daily")

    const handleFilterChange = (value: string, type: 'mode' | 'date' | 'status' | 'branch_id') => {
        const params = { ...filters, search: searchTerm };

        if (type === 'mode') {
            setMode(value);
            params.mode = value;
            // Reset date when switching modes to avoid invalid matches
            params.date = "";
        } else if (type === 'status') {
            params.status = value;
        } else if (type === 'branch_id') {
            params.branch_id = value;

            setSelectedBranch(branches.find((b) => b.id === Number(params.branch_id)));
        } else {
            params.date = value;
        }

        router.get(`/sales`, params, { preserveState: true, replace: true });
    }

    const clearFilters = () => {
        setMode("daily");

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
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    >
                        Invoice #
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                )
            },
        },
        {
            accessorKey: 'customer.full_name',
            header: 'Customer Name',
        },
        {
            accessorKey: 'branch.name',
            header: 'Branch',
        },
        {
            accessorKey: 'payment_type',
            header: 'Payment',
        },
        {
            accessorKey: 'amount_total',
            header: 'Total',
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }: any) => {
                const status = row.original.status.toLowerCase();
                const statusConfig = {
                    paid: "bg-green-100 text-green-700 border-green-200",
                    pending: "bg-yellow-100 text-yellow-700 border-yellow-200",
                    partial: "bg-blue-100 text-blue-700 border-blue-200",
                };
                const badgeStyle = statusConfig[status as keyof typeof statusConfig] || "bg-gray-100 text-gray-700";

                return (
                    <Badge className={`capitalize font-medium shadow-none border ${badgeStyle}`}>
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
            header: () => {
                const isSorted = filters.sort_field === 'transaction_date';

                return (
                    <Button
                        variant="ghost"
                        // Pass the field, the current filters object, and the route
                        onClick={() => sortBy('transaction_date', filters, 'sales.index')}
                        className="hover:bg-transparent p-0"
                    >
                        Date
                        <ArrowUpDown className={`ml-2 h-4 w-4 ${isSorted ? "text-primary" : "text-muted-foreground/50"}`} />
                    </Button>
                );
            },
        },
        {
            id: "payment",
            header: "Collection",
            cell: ({ row }: any) => {
                const status = row.original.status.toLowerCase();

                if ( status === 'paid') {
                    return;
                }

                return (
                    <Button
                        size="sm"
                        variant="default"
                        className="h-8 bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm"
                        onClick={() => handleReceivePayment(row.original)}
                    >
                        <CreditCard className="mr-2 h-3.5 w-3.5" />
                        Collect Pay
                    </Button>
                );
            }
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<any, any>) => {
                return (
                    <>
                        <Button variant="ghost" size="sm" onClick={() => openEditForm(row.original)}><Pencil /></Button>
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button variant="ghost" size="sm"><Trash2 /></Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        This action cannot be undone. This will permanently delete your
                                        user from our servers.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </>
                )
            }
        }
    ]

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sales" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Sale Management</h1>
                        <p className="text-sm text-muted-foreground">Manage your sale.</p>
                    </div>

                    {/* Create Staff Button */}
                    <Button onClick={() => openEditForm(null)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Transaction
                    </Button>
                </div>

                {/* Summary Stats Section */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card className="bg-sidebar border-sidebar-border">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between space-y-0 pb-2">
                                <p className="text-sm font-medium">Total Revenue {selectedBranch && `for ${selectedBranch.name}`}</p>
                                <Banknote className="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div className="flex items-baseline gap-2">
                                <h2 className="text-2xl font-bold">{formatCurrency(total_sales)}</h2>
                                <span className="text-xs text-muted-foreground">
                                    for selected {mode}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="bg-sidebar border-sidebar-border">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between space-y-0 pb-2">
                                <p className="text-sm font-medium">Total Balance</p>
                                <Banknote className="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div className="flex items-baseline gap-2">
                                <h2 className="text-2xl font-bold">{formatCurrency(total_balance)}</h2>
                                <span className="text-xs text-muted-foreground">
                                    for selected {mode}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Optional: Add more stats like Transaction Count */}
                    <Card className="bg-sidebar border-sidebar-border">
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between space-y-0 pb-2">
                                <p className="text-sm font-medium">Transactions</p>
                                <TrendingUp className="h-4 w-4 text-muted-foreground" />
                            </div>
                            <h2 className="text-2xl font-bold">{transactions.total || 0}</h2>
                        </CardContent>
                    </Card>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar">

                    <TableFilters searchTerm={searchTerm} setSearchTerm={setSearchTerm} mode={mode} filters={filters} handleFilterChange={handleFilterChange} clearFilters={clearFilters} branches={branches}/>

                    <DataTable columns={columns} pagination={transactions} />
                </div>
            </div>

            {isDialogOpen && (
                <SaleDialog open={isDialogOpen} setOpen={setIsDialogOpen} branches={branches} transaction={getTransaction}  customers={customers} />
            )}

            {isCollectPaymentDialogOpen && (
                <CollectPaymentDialog transaction={getTransaction}  open={isCollectPaymentDialogOpen} typesOfPayment={types_of_payment} setOpen={setIsCollectPaymentDialogOpen} />
            )}

        </AppLayout>
    );
}
