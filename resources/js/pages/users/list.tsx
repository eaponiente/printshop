import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import UserDialog from '@/pages/users/users-dialog';
import type { BreadcrumbItem } from '@/types';
import type { User, UsersList } from '@/types/user';
import {
    AlertDialog, AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
];

export default function UserIndex({ users, branches }: UsersList) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const [getUser, setUser] = useState<any | null>(null);
    const openEditForm = (user: any) => {
        setUser(user);
        setIsDialogOpen(true);
    };

    const deleteUser = (user: User) => {
        router.delete(`/users/${user.id}`, {
            onSuccess: () => toast.success('User deleted', { position: 'top-center' }),
        });
    }

    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'branch.name',
            header: 'Branch',
        },
        {
            accessorKey: 'last_name',
            header: 'Last Name',
        },
        {
            accessorKey: 'first_name',
            header: 'First Name',
        },
        {
            accessorKey: 'username',
            header: 'Username',
        },
        {
            accessorKey: 'role',
            header: 'Role',
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<any, any>) => {
                return (
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => openEditForm(row.original)}
                        >
                            <Pencil />
                        </Button>
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
                                        permanently delete your user from our
                                        servers.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>
                                        Cancel
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        onClick={() => deleteUser(row.original)}
                                    >
                                        Continue
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </>
                );
            },
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            User Management
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your staff and admins here.
                        </p>
                    </div>

                    {/* Create Staff Button */}
                    <Button onClick={() => openEditForm(null)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Staff
                    </Button>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={users} />
                </div>
            </div>
            {isDialogOpen && (
                <UserDialog
                    open={isDialogOpen}
                    setOpen={setIsDialogOpen}
                    user={getUser}
                    branches={branches}
                />
            )}
        </AppLayout>
    );
}
