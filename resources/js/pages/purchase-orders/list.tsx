import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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
import AppLayout from '@/layouts/app-layout';
import PurchaseOrderDialog from '@/pages/purchase-orders/purchase-order-dialog';
import type { BreadcrumbItem } from '@/types';
import type { PurchaseOrder, PurchaseOrdersList } from '@/types/purchase-order';
import { formatCurrency } from '@/utils/formatters';
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Purchase Orders', href: '/purchase-orders' },
];

export default function PurchaseOrderIndex({ purchase_orders, branches }: PurchaseOrdersList) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const [getPurchaseOrder, setPurchaseOrder] = useState<PurchaseOrder | null>(null);
    const openEditForm = (purchaseOrder: PurchaseOrder | null) => {
        setPurchaseOrder(purchaseOrder);
        setIsDialogOpen(true);
    };

    const deletePurchaseOrder = (purchaseOrder: PurchaseOrder) => {
        router.delete(`/purchase-orders/${purchaseOrder.id}`, {
            onSuccess: () => toast.success('Purchase Order deleted', { position: 'top-center'}),
        });
    }

    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'branch.name',
            header: 'Branch',
        },
        {
            accessorKey: 'particular',
            header: 'Particulars',
        },
        {
            accessorKey: 'grand_total',
            header: 'Total',
            cell: ({ row }: CellContext<any, any>) => {
                return formatCurrency(row.original.total_price);
            }
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }: CellContext<any, any>) => {
                const status = row.original.status.toLowerCase();

                // Mapping your Laravel Seeder statuses to Tailwind colors
                const statusConfig = {
                    pending: "bg-amber-100 text-amber-700 hover:bg-amber-200 border-amber-200",
                    active: "bg-blue-100 text-blue-700 hover:bg-blue-200 border-blue-200",
                    finished: "bg-emerald-100 text-emerald-700 hover:bg-emerald-200 border-emerald-200",
                    released: "bg-slate-100 text-slate-700 hover:bg-slate-200 border-slate-200",
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
            accessorKey: 'ordered_at',
            header: 'Ordered At'
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
                                    <AlertDialogAction onClick={() => deletePurchaseOrder(row.original)}>Continue</AlertDialogAction>
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
            <Head title="Purchase Orders" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Purchase Order Management</h1>
                        <p className="text-sm text-muted-foreground">Manage your purchase order.</p>
                    </div>

                    <Button onClick={() => openEditForm(null)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Purchase Order
                    </Button>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <div className="rounded-md border border-sidebar-border bg-sidebar">

                        <DataTable columns={columns} pagination={purchase_orders} />
                    </div>
                </div>


            </div>
            {isDialogOpen && (
                <PurchaseOrderDialog open={isDialogOpen} setOpen={setIsDialogOpen} order={getPurchaseOrder} branches={branches}/>
            )}

        </AppLayout>
    );
}
