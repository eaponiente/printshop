import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import UserDialog from '@/pages/users/users-dialog';
import type { BreadcrumbItem } from '@/types';
import type { User, UsersList } from '@/types/user';

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
            onSuccess: () => toast.success('User deleted', { position: 'top-center'}),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">User Management</h1>
                        <p className="text-sm text-muted-foreground">Manage your staff and admins here.</p>
                    </div>

                    {/* Create Staff Button */}
                    <Button onClick={() => openEditForm(null)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Staff
                    </Button>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Full Name</TableHead>
                                <TableHead>Username</TableHead>
                                <TableHead>Branch</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell className="font-medium">{user.fullname}</TableCell>
                                    <TableCell>{user.username}</TableCell>
                                    <TableCell>{user.branch?.name}</TableCell>
                                    <TableCell>
                                        <span className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-secondary text-secondary-foreground capitalize">
                                            {user.role}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button variant="ghost" size="sm" onClick={() => openEditForm(user)}><Pencil /></Button>
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
                                                    <AlertDialogAction onClick={() => deleteUser(user)}>Continue</AlertDialogAction>
                                                </AlertDialogFooter>
                                            </AlertDialogContent>
                                        </AlertDialog>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
            {isDialogOpen && (
                <UserDialog open={isDialogOpen} setOpen={setIsDialogOpen} user={getUser} branches={branches} />
            )}

        </AppLayout>
    );
}
