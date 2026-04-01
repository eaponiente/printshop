import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import {
    Banknote,
    Pencil,
    Plus,
    Trash2,
    TrendingUp,
    Wallet,
    XCircle,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
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
} from "@/components/ui/alert-dialog"
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import ExpenseDialog from '@/pages/expenses/expenses-dialog';
import type { BreadcrumbItem } from '@/types';
import type { Expense, ExpensesList } from '@/types/expenses';
import { formatCurrency } from '@/utils/formatters';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { route } from 'ziggy-js';
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
];

export default function ExpenseIndex({ expenses, branches, payment_methods, expenses_amount, filters }: ExpensesList) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const [getExpense, setExpense] = useState<any | null>(null);
    const openEditForm = (expense: Expense | null) => {
        setExpense(expense);
        setIsDialogOpen(true);
    };

    const deleteExpense = (expense: Expense) => {
        router.delete(`/expenses/${expense.id}`, {
            onSuccess: () => toast.success('Expense deleted', { position: 'top-center'}),
        });
    }

    const clearFilters = () => {
        router.get(route('expenses.index'), {}, { replace: true });
    };

    const handleFilterChange = (
        value: string,
        type: 'payment_type' | 'branch_id',
    ) => {
        const params = { ...filters };

        if (type === 'branch_id') {
            params.branch_id = value;
        } else if (type === 'payment_type') {
            params.payment_type = value;
        }

        router.get(`/expenses`, params, {
            preserveState: true,
            replace: true,
        });
    };

    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'amount',
            header: 'Amount',
            cell: ({ row }: CellContext<any, any>) => {
                return formatCurrency(row.original.amount);
            }
        },
        {
            accessorKey: 'branch.name',
            header: 'Branch',
        },
        {
            accessorKey: 'user.fullname',
            header: 'Staff',
        },
        {
            accessorKey: 'expense_date',
            header: 'Date Purchased'
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
                                    <AlertDialogAction onClick={() => deleteExpense(row.original)}>Continue</AlertDialogAction>
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
            <Head title="Expenses" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Expense Management
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your expense.
                        </p>
                    </div>

                    <Button onClick={() => openEditForm(null)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Expense
                    </Button>
                </div>

                <div className="grid gap-3 md:grid-cols-3">
                    {/* 1. Total Revenue - Ultra Compact */}
                    <Card className="border-sidebar-border bg-sidebar">
                        <CardContent className="p-3">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="mb-1 text-[10px] leading-none font-bold tracking-wider text-muted-foreground uppercase">
                                        Total Expenses
                                    </p>
                                    <h2 className="text-lg leading-none font-bold">
                                        {formatCurrency(expenses_amount)}
                                    </h2>
                                </div>
                                <Banknote className="h-4 w-4 text-primary/40" />
                            </div>
                            <p className="mt-1.5 truncate text-[9px] text-muted-foreground italic opacity-70"></p>
                        </CardContent>
                    </Card>
                </div>

                {/* 1. Increased padding from p-1 to p-4 or p-6 */}
                <div className="rounded-md border border-sidebar-border bg-sidebar p-4 lg:p-6">
                    {/* Filter Row: Flex container ensures everything stays beside each other */}
                    <div className="mb-6 flex flex-wrap items-end gap-4">
                        {/* 1. Branch Filter */}
                        <div className="flex flex-col gap-1.5">
                            <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                Branch
                            </label>
                            <Select
                                value={filters.branch_id || 'all'}
                                onValueChange={(v) =>
                                    handleFilterChange(v, 'branch_id')
                                }
                            >
                                <SelectTrigger className="h-10 w-[180px] bg-white text-sm shadow-sm">
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

                        {/* 2. Status Filter (New) */}
                        <div className="flex flex-col gap-1.5">
                            <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                Payment Method
                            </label>
                            <Select
                                value={filters.payment_type || 'all'}
                                onValueChange={(v) =>
                                    handleFilterChange(v, 'payment_type')
                                }
                            >
                                <SelectTrigger className="h-10 w-[150px] bg-white text-sm shadow-sm">
                                    <SelectValue placeholder="Payment_type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Mode
                                    </SelectItem>
                                    {payment_methods.map((p) => (
                                        <SelectItem key={p.key} value={p.key}>
                                            {p.value}
                                        </SelectItem>
                                    ))}
                                    <SelectItem value="paid">Paid</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* 3. Clear Button (Beside the next filter) */}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={clearFilters}
                            className="h-10 px-3 text-muted-foreground transition-colors hover:text-destructive"
                        >
                            <XCircle className="mr-2 h-4 w-4" />
                            Clear
                        </Button>
                    </div>

                    <DataTable columns={columns} pagination={expenses} />
                </div>
            </div>
            {isDialogOpen && (
                <ExpenseDialog
                    open={isDialogOpen}
                    branches={branches}
                    paymentMethods={payment_methods}
                    setOpen={setIsDialogOpen}
                    expense={getExpense}
                />
            )}
        </AppLayout>
    );
}
