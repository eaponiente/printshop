import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Check, ChevronDown, ExternalLink, Pencil, Plus, Trash2, XCircle } from 'lucide-react';
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
import { readableDate, toManilaTime } from '@/utils/dateHelper';
import CreatePoTransactionDialog from './components/create-po-transaction-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem } from '@radix-ui/react-dropdown-menu';
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Purchase Orders', href: '/purchase-orders' },
];

export default function PurchaseOrderIndex({ purchase_orders, branches, statuses, filters }: PurchaseOrdersList) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isMakeTransactionDialogOpen, setIsMakeTransactionDialogOpen] = useState(false);
    const [mode, setMode] = useState(filters.mode || 'monthly');

    const [getPurchaseOrder, setPurchaseOrder] = useState<PurchaseOrder | null>(null);
    const openEditForm = (purchaseOrder: PurchaseOrder | null) => {
        setPurchaseOrder(purchaseOrder);
        setIsDialogOpen(true);
    };

    const handleFilterChange = (
        value: string,
        type: 'branch_id' | 'date' | 'mode' | 'date_field' | 'po_number' | 'include_released',
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
        } else if (type === 'po_number') {
            params.po_number = value;
        } else if (type === 'include_released') {
            params.include_released = value;
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

    const showMakeTransactionDialog = (po: PurchaseOrder) => {
        setPurchaseOrder(po);
        setIsMakeTransactionDialogOpen(true);
    }

    const onMakeTransaction = (purchaseOrder: PurchaseOrder) => {
        router.post(route('purchase-orders.make-transaction', purchaseOrder.id), {}, {
            onSuccess: () => toast.success('Transaction created successfully', { position: 'top-center' }),
        });
    }

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
                const currentStatus = (row.original.status || 'pending').toLowerCase();

                const statusConfig = {
                    pending: { label: 'Pending', dot: 'bg-amber-500', styles: 'bg-amber-50 text-amber-700 border-amber-200' },
                    active: { label: 'Active', dot: 'bg-blue-500', styles: 'bg-blue-50 text-blue-700 border-blue-200' },
                    finished: { label: 'Finished', dot: 'bg-emerald-500', styles: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
                    released: { label: 'Released', dot: 'bg-slate-500', styles: 'bg-slate-50 text-slate-700 border-slate-200' },
                };

                const config = statusConfig[currentStatus as keyof typeof statusConfig] || statusConfig.pending;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button className={`
                        group inline-flex items-center justify-between w-32 px-3 py-1.5 
                        rounded-md border text-xs font-semibold 
                        transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500
                        ${config.styles}
                    `}>
                                <div className="flex items-center gap-2">
                                    <span className={`h-1.5 w-1.5 rounded-full ${config.dot}`} />
                                    <span className="capitalize">{config.label}</span>
                                </div>
                                <ChevronDown size={14} className="opacity-50 group-hover:opacity-100" />
                            </button>
                        </DropdownMenuTrigger>

                        {/* Added z-50 to ensure it's on top 
                  Added bg-white (or bg-popover) and border to fix transparency
                */}
                        <DropdownMenuContent
                            align="start"
                            sideOffset={4}
                            className="z-50 min-w-[8rem] overflow-hidden rounded-md border bg-white p-1 shadow-md animate-in fade-in-0 zoom-in-95"
                        >
                            <div className="px-2 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Update Status
                            </div>
                            {Object.entries(statusConfig).map(([key, value]) => (
                                <DropdownMenuItem
                                    key={key}
                                    onClick={() => router.patch(route('purchase-orders.status.update', row.original.id), { status: key }, { preserveScroll: true })}
                                    className="flex items-center gap-2 rounded px-2 py-2 text-sm cursor-pointer outline-none hover:bg-slate-100 focus:bg-slate-100 transition-colors"
                                >
                                    <span className={`h-2 w-2 rounded-full ${value.dot}`} />
                                    <span className="flex-1 font-medium text-slate-700">{value.label}</span>
                                    {currentStatus === key && <Check size={14} className="text-indigo-600" />}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
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
                const date = readableDate(row.original.received_at);

                return <span className={date.className}>{date.text}</span>;
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
                const date = readableDate(row.original.due_at);

                return <span className={date.className}>{date.text}</span>;
            },
        },
        {
            id: 'transaction',
            header: 'Transaction',
            cell: ({ row }) => {
                const po = row.original as PurchaseOrder;
                if (po.transaction) {
                    return (
                        <a
                            href={route('sales.index', {
                                search: po.transaction.invoice_number,
                                mode: 'yearly',
                            })}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700 transition-all hover:bg-indigo-100 hover:border-indigo-300 hover:shadow-sm"
                            title={`View Invoice: ${po.transaction.invoice_number}`}
                        >
                            <span>View Transaction</span>
                            <ExternalLink size={14} className="opacity-70" />
                        </a>
                    );
                }
                return (
                    // add color blue to button
                    <Button
                        variant="outline"
                        className="bg-blue-500 hover:bg-blue-600 hover:text-white"
                        onClick={() => showMakeTransactionDialog(po)}
                    >
                        Create Sale
                    </Button>
                );
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
                        {row.original.status.toLowerCase() === 'pending' && (
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
                        )}
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
                        {/* PO Number Filter */}
                        <div className="flex flex-col gap-1.5">
                            <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                PO Number
                            </label>
                            <Input
                                placeholder="Search PO Number..."
                                className="h-10 w-[200px] bg-white text-sm shadow-sm"
                                value={filters.po_number || ''}
                                onChange={(e) =>
                                    handleFilterChange(e.target.value, 'po_number')
                                }
                            />
                        </div>
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
                                    <SelectItem value="all">Select Date Field</SelectItem>
                                    <SelectItem value="due_at">
                                        Due Date
                                    </SelectItem>
                                    <SelectItem value="received_at">
                                        Received Date
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* New Checkbox Filter */}
                        <div className="flex h-10 items-center space-x-2 px-2">
                            <Checkbox
                                id="include_released"
                                checked={
                                    filters.include_released === 'true' ||
                                    filters.include_released === true
                                }
                                onCheckedChange={(checked) =>
                                    handleFilterChange(
                                        checked ? 'true' : 'false',
                                        'include_released',
                                    )
                                }
                            />
                            <label
                                htmlFor="include_released"
                                className="cursor-pointer text-sm leading-none font-medium text-muted-foreground transition-colors select-none hover:text-foreground"
                            >
                                Include Released
                            </label>
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

            {isMakeTransactionDialogOpen && getPurchaseOrder && (
                <CreatePoTransactionDialog
                    open={isMakeTransactionDialogOpen}
                    purchaseOrder={getPurchaseOrder}
                    setOpen={setIsMakeTransactionDialogOpen}
                />
            )}
        </AppLayout>
    );
}
