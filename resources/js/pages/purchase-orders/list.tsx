import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Pencil, Plus, Trash2, XCircle } from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
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
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import PurchaseOrderDialog from '@/pages/purchase-orders/purchase-order-dialog';
import type { BreadcrumbItem } from '@/types';
import type { PurchaseOrder, PurchaseOrdersList } from '@/types/purchase-order';
import { formatCurrency } from '@/utils/formatters';
import { sortBy } from '@/utils/helpers';
import { toManilaTime } from '@/utils/dateHelper';
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Purchase Orders', href: '/purchase-orders' },
];

export default function PurchaseOrderIndex({ purchase_orders, branches, statuses, filters }: PurchaseOrdersList) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [mode, setMode] = useState(filters.mode || 'monthly');

    const [getPurchaseOrder, setPurchaseOrder] = useState<PurchaseOrder | null>(null);
    const openEditForm = (purchaseOrder: PurchaseOrder | null) => {
        setPurchaseOrder(purchaseOrder);
        setIsDialogOpen(true);
    };

    const handleFilterChange = (
        value: string,
        type: 'branch_id' | 'date' | 'mode' | 'date_field',
    ) => {
        const params = { ...filters };

        if (type === 'branch_id') {
            params.branch_id = value;
        } else if (type === 'mode') {
            setMode(value);
            params.mode = value;
            // Reset date when switching modes to avoid invalid matches
            params.date = '';
        } else if (type === 'date') {
            params.date = value;
        } else if (type === 'date_field') {
            params.date_field = value;
        }

        router.get(route('purchase-orders.index'), params, {
            preserveState: true,
            replace: true,
        });
    };

    const deletePurchaseOrder = (purchaseOrder: PurchaseOrder) => {
        router.delete(`/purchase-orders/${purchaseOrder.id}`, {
            onSuccess: () => toast.success('Purchase Order deleted', { position: 'top-center' }),
        });
    }

    const clearFilters = () => {
        router.get(route('purchase-orders.index'), {}, { replace: true });
    };

    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'po_number',
            header: 'PO #',
        },
        {
            accessorKey: 'branch.name',
            header: 'Branch',
        },
        {
            accessorKey: 'grand_total',
            header: 'Total',
            cell: ({ row }: CellContext<any, any>) => {
                return formatCurrency(row.original.total_price);
            },
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }: CellContext<any, any>) => {
                const status = row.original.status.toLowerCase();

                // Mapping your Laravel Seeder statuses to Tailwind colors
                const statusConfig = {
                    pending:
                        'bg-amber-100 text-amber-700 hover:bg-amber-200 border-amber-200',
                    active: 'bg-blue-100 text-blue-700 hover:bg-blue-200 border-blue-200',
                    finished:
                        'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 border-emerald-200',
                    released:
                        'bg-slate-100 text-slate-700 hover:bg-slate-200 border-slate-200',
                };

                const badgeStyle =
                    statusConfig[status as keyof typeof statusConfig] ||
                    'bg-gray-100 text-gray-700';

                return (
                    <Badge
                        className={`border font-medium capitalize shadow-none ${badgeStyle}`}
                    >
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
            accessorKey: 'received_at',
            header: () => {
                const isSorted = filters.sort_field === 'received_at';

                return (
                    <Button
                        variant="ghost"
                        // Pass the field, the current filters object, and the route
                        onClick={() =>
                            sortBy(
                                'received_at',
                                filters,
                                'purchase-orders.index',
                            )
                        }
                        className="p-0 hover:bg-transparent"
                    >
                        Received At
                        <ArrowUpDown
                            className={`ml-2 h-4 w-4 ${isSorted ? 'text-primary' : 'text-muted-foreground/50'}`}
                        />
                    </Button>
                );
            },
            cell: ({ row }: any) => {
                return toManilaTime(row.original.received_at);
            },
        },
        {
            accessorKey: 'due_at',
            header: () => {
                const isSorted = filters.sort_field === 'due_at';

                return (
                    <Button
                        variant="ghost"
                        // Pass the field, the current filters object, and the route
                        onClick={() =>
                            sortBy('due_at', filters, 'purchase-orders.index')
                        }
                        className="p-0 hover:bg-transparent"
                    >
                        Due Date
                        <ArrowUpDown
                            className={`ml-2 h-4 w-4 ${isSorted ? 'text-primary' : 'text-muted-foreground/50'}`}
                        />
                    </Button>
                );
            },
            cell: ({ row }: any) => {
                return toManilaTime(row.original.due_at);
            },
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
                                        onClick={() =>
                                            deletePurchaseOrder(row.original)
                                        }
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
            <Head title="Purchase Orders" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Purchase Order Management
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your purchase order.
                        </p>
                    </div>

                    <Button onClick={() => openEditForm(null)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Purchase Order
                    </Button>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar p-2">
                    <div className="mb-6 flex flex-wrap items-end gap-4">
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

                        {/* Date Column Selection */}
                        <div className="flex flex-col gap-1.5">
                            <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                Filter By Date Type
                            </label>
                            <Select
                                value={filters.date_field || 'due_at'} // Default to 'date'
                                onValueChange={(v) =>
                                    handleFilterChange(v, 'date_field')
                                }
                            >
                                <SelectTrigger className="h-10 w-[180px] bg-white text-sm shadow-sm">
                                    <SelectValue placeholder="Select Date Field" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="due_at">
                                        Due Date
                                    </SelectItem>
                                    <SelectItem value="received_at">
                                        Received Date
                                    </SelectItem>
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
                                <SelectTrigger className="w-[140px] bg-white">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
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
                                        className="w-[180px] bg-white"
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
                                        className="w-[200px] bg-white"
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
                                        className="w-[180px] bg-white"
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

                    <DataTable columns={columns} pagination={purchase_orders} />
                </div>
            </div>
            {isDialogOpen && (
                <PurchaseOrderDialog
                    open={isDialogOpen}
                    statuses={statuses}
                    setOpen={setIsDialogOpen}
                    order={getPurchaseOrder}
                    branches={branches}
                />
            )}
        </AppLayout>
    );
}
