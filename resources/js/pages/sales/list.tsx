import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Plus } from 'lucide-react';
import { Banknote, TrendingUp } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DataTable } from '@/components/data-table';
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales', href: '/sales' },
];

interface SaleIndexProps {
    transactions: PaginatedResponse<Transaction>; // Use the generic here
    filters: any;
    branches: any[];
    customers: Customer[]
    total_sales: number;
    total_balance: number;
}

export default function SaleIndex({ transactions, filters, branches, customers, total_sales = 0, total_balance = 0 }: SaleIndexProps) {
    const openEditForm = () => {
        setIsDialogOpen(true);
    };

    // Currency Formatter
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'PHP', // Change to your currency, e.g., 'PHP'
        }).format(amount);
    };

    // 1. Add local state for the search input
    const [searchTerm, setSearchTerm] = useState(filters.search || "");
    const [selectedBranch, setSelectedBranch] = useState<Branch | null>(null);

    // 2. Debounce Search Logic: Updates the URL after user stops typing
    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (searchTerm !== (filters.search || "")) {
                router.get(`/sales`,
                    { ...filters, search: searchTerm, page: 1 },
                    { preserveState: true, replace: true }
                );
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [searchTerm]);

    const [isDialogOpen, setIsDialogOpen] = useState(false);

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

        router.get(`/sales`, {}, { replace: true });
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
            accessorKey: 'guest_name',
            header: 'Customer Name',
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
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const status = row.original.status.toLowerCase();

                // Define styles for each status
                const statusConfig = {
                    paid: "bg-green-100 text-green-700 hover:bg-green-200 border-green-200",
                    pending: "bg-yellow-100 text-yellow-700 hover:bg-yellow-200 border-yellow-200",
                    partial: "bg-blue-100 text-blue-700 hover:bg-blue-200 border-blue-200",
                };

                // Fallback style if status doesn't match
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
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    >
                        Date
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                )
            },
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
                    <Button onClick={() => openEditForm()}>
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

                    <TableFilters searchTerm={searchTerm} selectedBranch={selectedBranch} setSearchTerm={setSearchTerm} mode={mode} filters={filters} handleFilterChange={handleFilterChange} clearFilters={clearFilters} branches={branches}/>

                    <DataTable columns={columns} pagination={transactions} />
                </div>
            </div>

            {isDialogOpen && (
                <SaleDialog open={isDialogOpen} setOpen={setIsDialogOpen} branches={branches}  customers={customers} />
            )}

        </AppLayout>
    );
}
