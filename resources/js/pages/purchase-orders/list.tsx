import { Head, router } from '@inertiajs/react';
import {
    DropdownMenu,
    DropdownMenuTrigger,
    DropdownMenuContent,
    DropdownMenuItem,
} from '@radix-ui/react-dropdown-menu';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDown,
    Check,
    ChevronDown,
    ChevronsUpDown,
    ExternalLink,
    Pencil,
    Plus,
    Trash2,
    UserPlus,
    XCircle,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
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
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import PurchaseOrderDialog from '@/pages/purchase-orders/purchase-order-dialog';
import type { BreadcrumbItem } from '@/types';
import type { PurchaseOrder, PurchaseOrdersList } from '@/types/purchase-order';
import type { User } from '@/types/user';
import { readableDate } from '@/utils/dateHelper';
import { formatCurrency } from '@/utils/formatters';
import { getAvatarColor, sortBy } from '@/utils/helpers';
import CreatePoTransactionDialog from './components/create-po-transaction-dialog';
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Purchase Orders', href: '/purchase-orders' },
];

export default function PurchaseOrderIndex({
    purchase_orders,
    branches,
    filters,
    users,
}: PurchaseOrdersList) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isMakeTransactionDialogOpen, setIsMakeTransactionDialogOpen] =
        useState(false);
    const [mode, setMode] = useState(filters.mode || 'monthly');

    const [getPurchaseOrder, setPurchaseOrder] = useState<PurchaseOrder | null>(
        null,
    );
    const openEditForm = (purchaseOrder: PurchaseOrder | null) => {
        setPurchaseOrder(purchaseOrder);
        setIsDialogOpen(true);
    };

    const handleFilterChange = (
        value: string,
        type:
            | 'branch_id'
            | 'date'
            | 'mode'
            | 'date_field'
            | 'po_number'
            | 'include_released',
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
            onSuccess: () =>
                toast.success('Purchase Order deleted', {
                    position: 'top-center',
                }),
        });
    };

    const clearFilters = () => {
        router.get(route('purchase-orders.index'), {}, { replace: true });
    };

    // Debounced PO number search so we only hit the server after typing pauses.
    const [poSearch, setPoSearch] = useState(filters.po_number || '');

    useEffect(() => {
        const timeout = setTimeout(() => {
            if ((poSearch || '') !== (filters.po_number || '')) {
                handleFilterChange(poSearch, 'po_number');
            }
        }, 400);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [poSearch]);

    const showMakeTransactionDialog = (po: PurchaseOrder) => {
        setPurchaseOrder(po);
        setIsMakeTransactionDialogOpen(true);
    };

    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'po_number',
            header: 'PO #',
        },
        {
            accessorKey: 'Customer',
            header: 'Customer',
            cell: ({ row }: CellContext<any, any>) => {
                const customerName = row.original.customer?.first_name
                    ? `${row.original.customer?.full_name}`
                    : row.original.customer?.company;

                return (
                    <div
                        className="max-w-[120px] truncate"
                        title={customerName}
                    >
                        {customerName}
                    </div>
                );
            },
        },
        {
            accessorKey: 'branch.name',
            header: 'Branch',
            cell: ({ row }: CellContext<any, any>) => {
                const branchName = row.original.branch?.name;

                return (
                    <div className="max-w-[150px] truncate" title={branchName}>
                        {branchName}
                    </div>
                );
            },
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
                const currentStatus = (
                    row.original.status || 'pending'
                ).toLowerCase();

                const statusConfig = {
                    pending: {
                        label: 'Pending',
                        dot: 'bg-amber-500',
                        styles: 'bg-amber-50 text-amber-700 border-amber-200',
                    },
                    active: {
                        label: 'Active',
                        dot: 'bg-blue-500',
                        styles: 'bg-blue-50 text-blue-700 border-blue-200',
                    },
                    finished: {
                        label: 'Finished',
                        dot: 'bg-emerald-500',
                        styles: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    },
                    released: {
                        label: 'Released',
                        dot: 'bg-slate-500',
                        styles: 'bg-slate-50 text-slate-700 border-slate-200',
                    },
                };

                const config =
                    statusConfig[currentStatus as keyof typeof statusConfig] ||
                    statusConfig.pending;

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                className={`group inline-flex w-32 items-center justify-between rounded-md border px-3 py-1.5 text-xs font-semibold transition-all duration-200 focus:ring-2 focus:ring-indigo-500 focus:outline-none ${config.styles} `}
                            >
                                <div className="flex items-center gap-2">
                                    <span
                                        className={`h-1.5 w-1.5 rounded-full ${config.dot}`}
                                    />
                                    <span className="capitalize">
                                        {config.label}
                                    </span>
                                </div>
                                <ChevronDown
                                    size={14}
                                    className="opacity-50 group-hover:opacity-100"
                                />
                            </button>
                        </DropdownMenuTrigger>

                        {/* Added z-50 to ensure it's on top 
                  Added bg-white (or bg-popover) and border to fix transparency
                */}
                        <DropdownMenuContent
                            align="start"
                            sideOffset={4}
                            className="z-50 min-w-[8rem] animate-in overflow-hidden rounded-md border bg-white p-1 shadow-md fade-in-0 zoom-in-95"
                        >
                            <div className="px-2 py-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                                Update Status
                            </div>
                            {Object.entries(statusConfig).map(
                                ([key, value]) => (
                                    <DropdownMenuItem
                                        key={key}
                                        onClick={() =>
                                            router.patch(
                                                route(
                                                    'purchase-orders.status.update',
                                                    row.original.id,
                                                ),
                                                { status: key },
                                                { preserveScroll: true },
                                            )
                                        }
                                        className="flex cursor-pointer items-center gap-2 rounded px-2 py-2 text-sm transition-colors outline-none hover:bg-slate-100 focus:bg-slate-100"
                                    >
                                        <span
                                            className={`h-2 w-2 rounded-full ${value.dot}`}
                                        />
                                        <span className="flex-1 font-medium text-slate-700">
                                            {value.label}
                                        </span>
                                        {currentStatus === key && (
                                            <Check
                                                size={14}
                                                className="text-indigo-600"
                                            />
                                        )}
                                    </DropdownMenuItem>
                                ),
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },

        {
            accessorKey: 'assigned_user.fullname',
            header: () => {
                const isSorted = filters.sort_field === 'assigned_user_id';

                return (
                    <Button
                        variant="ghost"
                        onClick={() =>
                            sortBy(
                                'assigned_user_id',
                                filters,
                                'purchase-orders.index',
                            )
                        }
                        className="p-0 hover:bg-transparent"
                    >
                        Assigned To
                        <ArrowUpDown
                            className={`ml-2 h-4 w-4 ${isSorted ? 'text-primary' : 'text-muted-foreground/50'}`}
                        />
                    </Button>
                );
            },
            cell: ({ row }: CellContext<any, any>) => {
                const assignedUser = row.original.assigned_user;
                const recordId = row.original.id;

                const updateStaff = (userId: string | null) => {
                    router.patch(
                        route('purchase-orders.update-staff', recordId),
                        {
                            assigned_user_id: userId,
                        },
                        {
                            preserveScroll: true,
                            preserveState: true,
                            onError: (errors) => {
                                const message =
                                    errors.message ??
                                    Object.values(errors).flat()[0] ??
                                    'An error occurred';
                                toast.error(message, {
                                    position: 'top-center',
                                });
                            },
                        },
                    );
                };

                return (
                    <Popover>
                        <PopoverTrigger asChild>
                            <button
                                className={cn(
                                    'group flex w-fit max-w-[180px] items-center gap-2 rounded-full border px-2 py-1.5 transition-all duration-200',
                                    assignedUser
                                        ? 'border-input bg-background hover:border-primary/50 hover:shadow-sm'
                                        : 'border-dashed border-muted-foreground/30 bg-muted/30 hover:bg-muted/50',
                                )}
                            >
                                <div
                                    className={cn(
                                        'flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[9px] font-bold text-white uppercase transition-colors',
                                        !assignedUser &&
                                            'bg-muted-foreground/20 text-muted-foreground',
                                    )}
                                    style={
                                        assignedUser
                                            ? {
                                                  backgroundColor:
                                                      getAvatarColor(
                                                          assignedUser.fullname,
                                                      ),
                                              }
                                            : {}
                                    }
                                >
                                    {assignedUser ? (
                                        assignedUser.fullname.substring(0, 2)
                                    ) : (
                                        <UserPlus className="h-3 w-3" />
                                    )}
                                </div>

                                <span
                                    className={cn(
                                        'truncate pr-1 text-xs',
                                        assignedUser
                                            ? 'font-medium text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {assignedUser
                                        ? assignedUser.fullname
                                        : 'Assign Staff'}
                                </span>

                                <ChevronsUpDown className="h-3 w-3 text-muted-foreground/50 opacity-0 transition-opacity group-hover:opacity-100" />
                            </button>
                        </PopoverTrigger>

                        <PopoverContent
                            className="w-[240px] p-0 shadow-lg"
                            align="start"
                        >
                            <div className="border-b bg-muted/20 p-1.5">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 w-full justify-start text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    onClick={() => updateStaff(null)}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 h-3.5 w-3.5',
                                            !assignedUser
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    Remove Assignment
                                </Button>
                            </div>

                            <Command>
                                <CommandInput
                                    placeholder="Search users..."
                                    className="h-9"
                                />
                                <CommandList className="max-h-[250px]">
                                    <CommandEmpty>No users found.</CommandEmpty>
                                    <CommandGroup heading="Available Staff">
                                        {users.map((user: User) => (
                                            <CommandItem
                                                key={user.id}
                                                value={user.fullname}
                                                onSelect={() =>
                                                    updateStaff(
                                                        user.id.toString(),
                                                    )
                                                }
                                                className="flex cursor-pointer items-center gap-2 px-2 py-2"
                                            >
                                                <div
                                                    className="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-semibold text-white uppercase"
                                                    style={{
                                                        backgroundColor:
                                                            getAvatarColor(
                                                                user.fullname,
                                                            ),
                                                    }}
                                                >
                                                    {user.fullname.substring(
                                                        0,
                                                        2,
                                                    )}
                                                </div>
                                                <span className="flex-1 truncate">
                                                    {user.fullname}
                                                </span>
                                                {assignedUser?.id ===
                                                    user.id && (
                                                    <Check className="h-4 w-4 text-primary" />
                                                )}
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                );
            },
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
            id: 'transaction_lean_widget',
            header: 'Transaction & Status',
            cell: ({ row }: CellContext<any, any>) => {
                const po = row.original;
                const transaction = po.transaction;
                const status = transaction?.status;

                const colors = {
                    paid: 'bg-emerald-500 text-emerald-600 border-emerald-100',
                    pending: 'bg-amber-500 text-amber-600 border-amber-100',
                    partial: 'bg-blue-500 text-blue-600 border-blue-100',
                    none: 'bg-slate-300 text-slate-400 border-slate-100',
                };

                const theme =
                    colors[status as keyof typeof colors] || colors.none;

                return (
                    <div className="flex min-w-[200px] items-center gap-3">
                        {/* 1. Slim Status Indicator */}
                        <div className="flex shrink-0 flex-col items-center gap-1">
                            <div
                                className={`h-8 w-1 rounded-full ${theme.split(' ')[0]}`}
                            />
                        </div>

                        <div className="flex flex-1 items-center justify-between">
                            {/* 2. Info Block */}
                            <div className="flex flex-col">
                                <span
                                    className={`text-[10px] font-bold tracking-widest uppercase ${theme.split(' ')[1]}`}
                                >
                                    {status || 'No Sale'}
                                </span>
                                {transaction ? (
                                    <span className="text-sm font-semibold tracking-tight text-slate-700">
                                        {transaction.invoice_number}
                                    </span>
                                ) : (
                                    <span className="text-xs text-slate-400 italic">
                                        Pending Entry
                                    </span>
                                )}
                            </div>

                            <div className="flex items-center pl-4">
                                {transaction ? (
                                    <a
                                        href={route('sales.index', {
                                            search: transaction.invoice_number,
                                            tab:
                                                transaction?.payments_count ||
                                                0 > 0
                                                    ? 'payments'
                                                    : 'unpaid',
                                        })}
                                        target="_blank"
                                        className="group flex h-8 items-center gap-2 rounded-md px-2 text-xs font-bold text-slate-500 transition-all hover:bg-slate-50 hover:text-indigo-600"
                                    >
                                        <span>VIEW</span>
                                        <ExternalLink
                                            size={12}
                                            className="-translate-x-1 opacity-0 transition-all group-hover:translate-x-0 group-hover:opacity-100"
                                        />
                                    </a>
                                ) : (
                                    <button
                                        onClick={() =>
                                            showMakeTransactionDialog(po)
                                        }
                                        className="flex h-8 items-center gap-1.5 rounded-md border border-blue-200 bg-white px-3 text-xs font-bold text-blue-600 shadow-sm transition-all hover:bg-blue-600 hover:text-white"
                                    >
                                        <Plus size={14} strokeWidth={2.5} />
                                        CREATE
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
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
                                            This action cannot be undone. This
                                            will permanently delete your user
                                            from our servers.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Cancel
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() =>
                                                deletePurchaseOrder(
                                                    row.original,
                                                )
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
                    <div className="mb-6 flex flex-wrap items-end gap-3">
                        {/* PO Number Filter */}
                        <div className="flex flex-col gap-1.5">
                            <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                PO Number
                            </label>
                            <Input
                                placeholder="Search PO Number..."
                                className="h-10 w-[200px] bg-white text-sm shadow-sm"
                                value={poSearch}
                                onChange={(e) => setPoSearch(e.target.value)}
                            />
                        </div>

                        {/* Branch Filter */}
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
                                value={filters.date_field || 'due_at'}
                                onValueChange={(v) =>
                                    handleFilterChange(v, 'date_field')
                                }
                            >
                                <SelectTrigger className="h-10 w-[180px] bg-white text-sm shadow-sm">
                                    <SelectValue placeholder="Select Date Field" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Select Date Field
                                    </SelectItem>
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
                        <div className="flex flex-col gap-1.5">
                            <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                Frequency
                            </label>
                            <Select
                                value={mode}
                                onValueChange={(v) =>
                                    handleFilterChange(v, 'mode')
                                }
                            >
                                <SelectTrigger className="h-10 w-[140px] bg-white text-sm shadow-sm">
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

                        {/* Dynamic Date Selection */}
                        <div className="flex flex-col gap-1.5">
                            <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
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
                                        className="h-10 w-[180px] bg-white text-sm shadow-sm"
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
                                        className="h-10 w-[200px] bg-white text-sm shadow-sm"
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
                                        className="h-10 w-[180px] bg-white text-sm shadow-sm"
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
                                        className="h-10 w-[180px] rounded-md border bg-white px-3 py-2 text-sm shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
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

                        {/* Checkbox Filter - Adjusted to align with Inputs */}
                        <div className="flex h-10 items-center space-x-2 px-2 pb-0.5">
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
                                className="cursor-pointer text-sm font-medium text-muted-foreground transition-colors select-none hover:text-foreground"
                            >
                                Include Released
                            </label>
                        </div>

                        {/* Clear Button */}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={clearFilters}
                            className="h-10 px-3 text-muted-foreground hover:text-destructive"
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
