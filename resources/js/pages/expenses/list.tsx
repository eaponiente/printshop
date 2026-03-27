import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
import AppLayout from '@/layouts/app-layout';
import ExpenseDialog from '@/pages/expenses/expenses-dialog';
import type { BreadcrumbItem } from '@/types';
import type { Expense, ExpensesList } from '@/types/expenses';
import { formatCurrency } from '@/utils/formatters';
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expenses', href: '/expenses' },
];

export default function ExpenseIndex({ expenses, branches, payment_methods }: ExpensesList) {
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
            accessorKey: 'created_at',
            header: 'Created At'
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

                <div className="rounded-md border border-sidebar-border bg-sidebar p-1">
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
