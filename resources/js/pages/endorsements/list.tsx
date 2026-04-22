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
import EndorsementDialog from '@/pages/endorsements/endorsements-dialog';
import type { BreadcrumbItem } from '@/types';
import type { Endorsement, EndorsementsList } from '@/types/endorsements';
import { formatCurrency } from '@/utils/formatters';
import { toManilaTime } from '@/utils/dateHelper';
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Endorsements', href: '/endorsements' },
];

export default function EndorsementIndex({ endorsements, branches }: EndorsementsList) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const [getEndorsement, setEndorsement] = useState<any | null>(null);
    const openEditForm = (endorsement: any) => {
        setEndorsement(endorsement);
        setIsDialogOpen(true);
    };

    const deleteEndorsement = (endorsement: Endorsement) => {
        router.delete(`/endorsements/${endorsement.id}`, {
            onSuccess: () => toast.success('Endorsement deleted', { position: 'top-center' }),
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
            accessorKey: 'created_at',
            header: 'Created At',
            cell: ({ row }: CellContext<any, any>) => {
                return toManilaTime(row.original.created_at);
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
                                    <AlertDialogAction onClick={() => deleteEndorsement(row.original)}>Continue</AlertDialogAction>
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
            <Head title="Endorsements" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Endorsement Management</h1>
                        <p className="text-sm text-muted-foreground">Manage your endorsement.</p>
                    </div>

                    <Button onClick={() => openEditForm(null)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Endorsement
                    </Button>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar p-1">
                    <DataTable columns={columns} pagination={endorsements} />
                </div>
            </div>
            {isDialogOpen && (
                <EndorsementDialog open={isDialogOpen} branches={branches} setOpen={setIsDialogOpen} endorsement={getEndorsement} />
            )}

        </AppLayout>
    );
}
