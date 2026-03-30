import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import { DataTable } from '@/components/data-table';
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel,
    AlertDialogContent, AlertDialogDescription, AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Customer, CustomersList } from '@/types/user';
import CustomerDialog from '@/pages/customers/customer-dialog';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
];

export default function CustomerIndex({ customers }: CustomersList) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const [getCustomer, setCustomer] = useState<any | null>(null);
    const openEditForm = (customer: any) => {
        setCustomer(customer);
        setIsDialogOpen(true);
    };

    const deleteCustomer = (customer: Customer) => {
        router.delete(`/customers/${customer.id}`, {
            onSuccess: () => toast.success('Customer deleted', { position: 'top-center'}),
            onError: (errors) => {
                if (errors.delete) {
                    toast.error('Action Denied', {
                        description: errors.delete,
                        position: 'top-center'
                    });
                } else {
                    toast.error('An unexpected error occurred.');
                }
            },
        });
    }

    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'first_name',
            header: 'First Name',
        },
        {
            accessorKey: 'last_name',
            header: 'Last Name',
        },
        {
            accessorKey: 'company',
            header: 'Company',
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
                                    <AlertDialogAction onClick={() => deleteCustomer(row.original)}>Continue</AlertDialogAction>
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
            <Head title="Customers" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Customer Management</h1>
                        <p className="text-sm text-muted-foreground">Manage your customer.</p>
                    </div>

                    {/* Create Staff Button */}
                    <Button onClick={() => openEditForm(null)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Customer
                    </Button>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={customers} />
                </div>
            </div>
            {isDialogOpen && (
                <CustomerDialog open={isDialogOpen} setOpen={setIsDialogOpen} customer={getCustomer} />
            )}

        </AppLayout>
    );
}
