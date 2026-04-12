import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Banknote, Plus, XCircle } from 'lucide-react';
import React, { useState } from 'react';
import { route } from 'ziggy-js';
import { DataTable } from '@/components/data-table';
import { Badge } from "@/components/ui/badge"; // Assuming Shadcn Badge
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import ExpenseActions from '@/pages/expenses/components/expense-actions';
import ExpenseDialog from '@/pages/expenses/expenses-dialog';
import type { BreadcrumbItem } from '@/types';
import type { Expense, ExpensesList } from '@/types/expenses';
import { toManilaTime } from '@/utils/dateHelper';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
];

export default function ExpenseIndex({
    expenses,
    branches,
    payment_methods,
    expenses_amount,
    filters,
}: ExpensesList) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [mode, setMode] = useState(filters.mode || 'daily');
    const [getExpense, setExpense] = useState<any | null>(null);
    const openEditForm = (expense: Expense | null) => {
        setExpense(expense);
        setIsDialogOpen(true);
    };

    const clearFilters = () => {
        router.get(route('expenses.index'), {}, { replace: true });
    };

    const handleFilterChange = (
        value: string,
        type: 'payment_type' | 'branch_id' | 'date' | 'mode',
    ) => {
        const params = { ...filters };

        if (type === 'branch_id') {
            params.branch_id = value;
        } else if (type === 'payment_type') {
            params.payment_type = value;
        } else if (type === 'mode') {
            setMode(value);
            params.mode = value;
            // Reset date when switching modes to avoid invalid matches
            params.date = '';
        } else if (type === 'date') {
            params.date = value;
        }

        router.get(`/expenses`, params, {
            preserveState: true,
            replace: true,
        });
    };

    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'branch.name',
            header: 'Branch',
        },
        {
            accessorKey: 'amount',
            header: 'Amount',
            cell: ({ row }: CellContext<any, any>) => {
                return formatCurrency(row.original.amount);
            },
        },
        {
            accessorKey: 'user.fullname',
            header: 'Staff',
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const status = row.getValue('status') as string;

                const statusConfig: Record<
                    string,
                    { label: string; className: string }
                > = {
                    paid: {
                        label: 'Paid',
                        className:
                            'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 border-emerald-200',
                    },
                    void: {
                        label: 'Voided',
                        className:
                            'bg-destructive/10 text-destructive hover:bg-destructive/20 border-destructive/20',
                    },
                };

                const config = statusConfig[status] || {
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
        {
            accessorKey: 'expense_date',
            header: 'Date Purchased',
            cell: ({ row }: any) => {
                return toManilaTime(row.original.expense_date);
            },
        },
        {
            accessorKey: 'void_reason',
            header: 'Void Reason'
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<any, any>) => {
                const expense = row.original;

                if (expense.status === 'void') {
                    return null;
                }

                return (
                    <ExpenseActions
                        expense={expense}
                        onEdit={(expense) => openEditForm(expense)}
                    />
                );
            },
        },
    ];

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
                                <SelectTrigger className="h-10 w-37.5 bg-white text-sm shadow-sm">
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

                        {/* Mode Selection */}
                        <div className="space-y-1.5">
                            <label className="ml-1 text-xs font-semibold text-muted-foreground uppercase">
                                Frequency
                            </label>
                            <Select
                                value={mode}
                                onValueChange={(v) =>
                                    handleFilterChange(v, 'mode')
                                }
                            >
                                <SelectTrigger className="w-35 bg-white">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="daily">Daily</SelectItem>
                                    <SelectItem value="weekly">
                                        Weekly
                                    </SelectItem>
                                    <SelectItem value="monthly">
                                        Monthly
                                    </SelectItem>
                                    <SelectItem value="yearly">
                                        Yearly
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Date Selection - Placed directly beside */}
                        <div className="space-y-1.5">
                            <label className="ml-1 text-xs font-semibold text-muted-foreground uppercase">
                                Select {mode}
                            </label>
                            <div className="flex items-center gap-2">
                                {mode === 'daily' && (
                                    <Input
                                        type="date"
                                        value={filters.date || ''}
                                        onChange={(e) =>
                                            handleFilterChange(
                                                e.target.value,
                                                'date',
                                            )
                                        }
                                        className="w-45 bg-white"
                                    />
                                )}
                                {mode === 'weekly' && (
                                    <Input
                                        type="week"
                                        value={filters.date || ''}
                                        onChange={(e) =>
                                            handleFilterChange(
                                                e.target.value,
                                                'date',
                                            )
                                        }
                                        className="w-50 bg-white"
                                    />
                                )}
                                {mode === 'monthly' && (
                                    <Input
                                        type="month"
                                        value={filters.date || ''}
                                        onChange={(e) =>
                                            handleFilterChange(
                                                e.target.value,
                                                'date',
                                            )
                                        }
                                        className="w-45 bg-white"
                                    />
                                )}
                                {mode === 'yearly' && (
                                    <select
                                        value={
                                            filters.date
                                                ? filters.date.substring(0, 4)
                                                : new Date().getFullYear()
                                        }
                                        onChange={(e) =>
                                            handleFilterChange(
                                                e.target.value,
                                                'date',
                                            )
                                        }
                                        className="h-10 w-[180px] rounded-md border bg-white px-3 py-2 shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                                    >
                                        {Array.from({ length: 6 }, (_, i) => {
                                            const year =
                                                new Date().getFullYear() - i;

                                            return (
                                                <option key={year} value={year}>
                                                    {year}
                                                </option>
                                            );
                                        })}
                                    </select>
                                )}
                            </div>
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
