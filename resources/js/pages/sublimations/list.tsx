import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDown,
    Check,
    ChevronDown,
    ChevronsUpDown,
    ExternalLink,
    Images,
    Pencil,
    Plus,
    Trash2,
    UserPlus,
    X,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';
import { DataTable } from '@/components/data-table';
import TagSelector from '@/components/shared/tag-selector';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
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
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { StatusFilter } from '@/pages/sublimations/components/status-filter';
import { StatusCell } from '@/pages/sublimations/status-cell';
import SublimationDialog from '@/pages/sublimations/sublimation-dialog';
import SublimationGallery from '@/pages/sublimations/sublimation-gallery';
import type { BreadcrumbItem } from '@/types';
import type { Branch } from '@/types/branches';
import type { UploadedImage } from '@/types/images';
import type { PaginatedResponse } from '@/types/pagination';
import type { Tag } from '@/types/settings';
import type { Sublimation } from '@/types/sublimations';
import type { User } from '@/types/user';
import { toManilaTime } from '@/utils/dateHelper';
import { getCustomerDisplayName } from '@/utils/formatters';
import { getAvatarColor, sortBy } from '@/utils/helpers';
import { EditableDateCell } from './components/editable-date-cell';
import { TagCell } from './tag-cell';

function computeNextAdditionalDescription(description: string): string {
    const m = description.match(/^(.*) \(Addtl\. (\d+)\)$/);
    if (m) {
        return `${m[1]} (Addtl. ${parseInt(m[2], 10) + 1})`;
    }
    return `${description} (Addtl. 1)`;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sublimations', href: '/sublimations' },
];

interface SublimationIndexProps {
    sublimations: PaginatedResponse<Sublimation>;
    branches: Branch[];
    filters: any;
    availableTags: Tag[];
    users: User[];
    statuses: { key: string; value: string; color: string }[];
}

export default function SublimationIndex({
    sublimations,
    branches,
    filters,
    statuses,
    users,
    availableTags,
}: SublimationIndexProps) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [selectedSublimation, setSelectedSublimation] =
        useState<Sublimation | null>(null);
    const [zoomedImage, setZoomedImage] = useState<UploadedImage | null>(null);

    const [duplicateTarget, setDuplicateTarget] = useState<Sublimation | null>(null);
    const [duplicateDescription, setDuplicateDescription] = useState('');
    const [duplicateTagQtys, setDuplicateTagQtys] = useState<Record<number, string>>({});
    const [duplicateAmount, setDuplicateAmount] = useState('');
    const [duplicating, setDuplicating] = useState(false);

    const openDuplicateDialog = (sub: Sublimation) => {
        setDuplicateTarget(sub);
        setDuplicateDescription(
            computeNextAdditionalDescription(sub.description ?? ''),
        );
        setDuplicateTagQtys(
            Object.fromEntries(
                (sub.tags ?? []).map((t) => [t.id, String(t.pivot?.quantity ?? 1)]),
            ),
        );
        setDuplicateAmount('');
    };

    const addDuplicateTag = (tagId: number) =>
        setDuplicateTagQtys((prev) =>
            prev[tagId] !== undefined ? prev : { ...prev, [tagId]: '1' },
        );

    const removeDuplicateTag = (tagId: number) =>
        setDuplicateTagQtys((prev) => {
            const next = { ...prev };
            delete next[tagId];
            return next;
        });

    const updateDuplicateTagQty = (tagId: number, qty: number) =>
        setDuplicateTagQtys((prev) => ({ ...prev, [tagId]: String(qty) }));

    const duplicateSelectedTagIds = Object.keys(duplicateTagQtys).map(Number);

    const duplicateQuantitiesMap: Record<number, number> = Object.fromEntries(
        Object.entries(duplicateTagQtys).map(([id, qty]) => [
            Number(id),
            Number(qty) || 1,
        ]),
    );

    const duplicateTotalQty = Object.values(duplicateTagQtys).reduce(
        (sum, q) => sum + (Number(q) || 0),
        0,
    );

    const [deleteTarget, setDeleteTarget] = useState<Sublimation | null>(null);

    const [isGalleryOpen, setIsGalleryOpen] = useState(false);
    const [gallerySublimation, setGallerySublimation] =
        useState<Sublimation | null>(null);

    const openGallery = (sublimation: Sublimation) => {
        setGallerySublimation(sublimation);
        setIsGalleryOpen(true);
    };

    const openCreateForm = () => {
        setSelectedSublimation(null);
        setIsDialogOpen(true);
    };

    const openEditForm = (sublimation: Sublimation) => {
        setSelectedSublimation(sublimation);
        setIsDialogOpen(true);
    };

    const deleteSublimation = (sublimation: Sublimation) => {
        router.delete(route('sublimations.destroy', sublimation.id), {
            onSuccess: () =>
                toast.success('Sublimation deleted', {
                    position: 'top-center',
                }),
            onError: (errors) =>
                toast.error(errors.message, { position: 'top-center' }),
        });
    };

    const [branchOpen, setBranchOpen] = useState(false);
    const selectedValues: string[] = Array.isArray(filters.branch_id)
        ? filters.branch_id
        : [];

    const toggleBranch = (id: string) => {
        const newSelection: string[] = selectedValues.includes(id)
            ? selectedValues.filter((item: string) => item !== id)
            : [...selectedValues, id];

        handleFilterChange(newSelection, 'branch_id');
    };

    const handleFilterChange = (
        // Update value to accept string or string array
        value: string | string[] | boolean,
        type: 'date' | 'status' | 'branch_id' | 'include_completed' | 'user_id',
    ) => {
        // Clone filters
        const params = { ...filters };

        // You can simplify this logic significantly
        params[type] = value;

        // IMPORTANT: If you are using Inertia.js or a similar router,
        // it will automatically convert ['active', 'pending'] into
        // ?status[]=active&status[]=pending in the URL.
        router.get(`/sublimations`, params, {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        router.get(route('sublimations.index'), {}, { replace: true });
    };

    const columns: ColumnDef<any>[] = [
        {
            accessorKey: 'customer.full_name',
            header: 'Customer',
            cell: ({ row }: CellContext<any, any>) => {
                const customerName = getCustomerDisplayName(
                    row.original.customer,
                );

                return (
                    <div
                        className="max-w-[150px] truncate"
                        title={customerName}
                    >
                        {customerName}
                    </div>
                );
            },
        },
        {
            accessorKey: 'tags',
            header: 'Category',
            cell: ({ row }: CellContext<any, any>) => {
                return (
                    <TagCell
                        sublimation={row.original}
                        allTags={availableTags}
                    />
                );
            },
        },
        {
            accessorKey: 'description',
            header: 'Team / Subject',
            cell: ({ row }: CellContext<any, any>) => {
                const description = row.original.description;

                return (
                    <div className="max-w-[150px] truncate" title={description}>
                        {description}
                    </div>
                );
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
                            sortBy('due_at', filters, 'sublimations.index')
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
            cell: ({ row }) => (
                <EditableDateCell
                    recordId={row.original.id}
                    dueAt={row.original.due_at}
                />
            ),
        },
        {
            accessorKey: 'created_at',
            header: () => {
                const isSorted = filters.sort_field === 'created_at';

                return (
                    <Button
                        variant="ghost"
                        onClick={() =>
                            sortBy('created_at', filters, 'sublimations.index')
                        }
                        className="p-0 hover:bg-transparent"
                    >
                        Created At
                        <ArrowUpDown
                            className={`ml-2 h-4 w-4 ${isSorted ? 'text-primary' : 'text-muted-foreground/50'}`}
                        />
                    </Button>
                );
            },
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-sm tabular-nums">
                    {toManilaTime(row.original.created_at)}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => (
                <StatusCell item={row.original} statuses={statuses} />
            ),
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
            accessorKey: 'user.fullname',
            header: () => {
                const isSorted = filters.sort_field === 'user_id';

                return (
                    <Button
                        variant="ghost"
                        // Pass the field, the current filters object, and the route
                        onClick={() =>
                            sortBy('user_id', filters, 'sublimations.index')
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
            cell: ({ row }) => {
                const assignedUser = row.original.user;
                const recordId = row.original.id;

                const updateStaff = (userId: string | null) => {
                    router.patch(
                        route('sublimations.update-staff', recordId),
                        {
                            user_id: userId,
                        },
                        {
                            preserveScroll: true,
                            preserveState: true,
                            onError: (errors) => {
                                const message =
                                    errors.user_id ??
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
                                {/* Avatar Circle - Now with Dynamic Background */}
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

                                {/* Name Label */}
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
                            {/* Header Action: Unassign */}
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
                                        {users.map((user) => (
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
                                                {/* Dropdown Avatar Color */}
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
            id: 'transaction_lean_widget',
            header: 'Transaction & Status',
            cell: ({ row }: CellContext<any, any>) => {
                const po = row.original;
                const transaction = po.transaction;
                const status = transaction?.status;
                console.log('tt', transaction?.payments_count || 0);

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
                                {transaction && (
                                    <a
                                        href={route('sales.index', {
                                            search: transaction.invoice_number,
                                            tab:
                                                transaction?.amount_paid > 0
                                                    ? 'payments'
                                                    : 'unpaid',
                                            mode: 'yearly',
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
                const prePaymentKeys = [
                    'for_approval',
                    'done_layout',
                    'waiting_for_dp',
                ];

                const canDelete = prePaymentKeys.includes(row.original.status);

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                Actions
                                <ChevronDown className="ml-1 h-4 w-4 opacity-70" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuItem
                                onSelect={() => openGallery(row.original)}
                                className="cursor-pointer"
                            >
                                <Images className="mr-2 h-4 w-4" />
                                Open Gallery
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => openEditForm(row.original)}
                                className="cursor-pointer"
                            >
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() =>
                                    openDuplicateDialog(row.original)
                                }
                                className="cursor-pointer"
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Items
                            </DropdownMenuItem>
                            {canDelete && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            setDeleteTarget(row.original)
                                        }
                                        className="cursor-pointer text-destructive focus:text-destructive"
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Sublimations" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Sublimations</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your sublimation.
                        </p>
                    </div>

                    <Button onClick={openCreateForm}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add sublimation
                    </Button>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar p-1">
                    <div className="mb-6 flex flex-wrap items-end gap-3 rounded-lg bg-slate-50/50">
                        <div className="mb-1 flex flex-wrap items-end gap-3 rounded-lg bg-slate-50/50 p-4">
                            {/* Branch Filter */}
                            <div className="flex flex-col gap-1.5">
                                <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                    Branches
                                </label>
                                <Popover
                                    open={branchOpen}
                                    onOpenChange={setBranchOpen}
                                >
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            role="combobox"
                                            aria-expanded={branchOpen}
                                            className="h-auto min-h-10 w-[200px] justify-between bg-white px-3 py-2 text-sm"
                                        >
                                            <div className="flex flex-wrap gap-1">
                                                {selectedValues.length === 0 &&
                                                    'All Branches'}
                                                {selectedValues.map((id) => (
                                                    <Badge
                                                        variant="secondary"
                                                        key={id}
                                                        className="font-normal"
                                                    >
                                                        {
                                                            branches.find(
                                                                (b) =>
                                                                    String(
                                                                        b.id,
                                                                    ) ===
                                                                    String(id),
                                                            )?.name
                                                        }
                                                    </Badge>
                                                ))}
                                            </div>
                                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-[200px] p-0">
                                        <Command>
                                            <CommandInput placeholder="Search branch..." />
                                            <CommandList>
                                                <CommandEmpty>
                                                    No branch found.
                                                </CommandEmpty>
                                                <CommandGroup>
                                                    {branches.map((branch) => {
                                                        const isSelected =
                                                            selectedValues.includes(
                                                                String(
                                                                    branch.id,
                                                                ),
                                                            );

                                                        return (
                                                            <CommandItem
                                                                key={branch.id}
                                                                onSelect={() =>
                                                                    toggleBranch(
                                                                        String(
                                                                            branch.id,
                                                                        ),
                                                                    )
                                                                }
                                                                className="flex items-center gap-2"
                                                            >
                                                                {/* The Checkbox Component */}
                                                                <Checkbox
                                                                    checked={
                                                                        isSelected
                                                                    }
                                                                    id={`branch-${branch.id}`}
                                                                />
                                                                <span className="flex-1">
                                                                    {
                                                                        branch.name
                                                                    }
                                                                </span>
                                                            </CommandItem>
                                                        );
                                                    })}
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            </div>

                            {/* User Filter - Only shows when a specific branch is selected */}
                            <div className="flex flex-col gap-1.5">
                                <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                    User / Staff
                                </label>
                                <Select
                                    value={filters.user_id || 'all'}
                                    onValueChange={(v) =>
                                        handleFilterChange(
                                            v === 'all' ? '' : v,
                                            'user_id',
                                        )
                                    }
                                >
                                    <SelectTrigger className="h-10 w-[160px] bg-white text-sm">
                                        <SelectValue placeholder="All Users" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Users
                                        </SelectItem>
                                        <SelectItem value="unassigned">
                                            Unassigned
                                        </SelectItem>
                                        {users.map((user: User) => (
                                            <SelectItem
                                                key={user.id}
                                                value={String(user.id)}
                                            >
                                                {user.fullname}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Status Filter */}
                            <StatusFilter
                                filters={filters}
                                statuses={statuses}
                                handleFilterChange={(value: string) =>
                                    handleFilterChange(value, 'status')
                                }
                            />

                            {/* New Checkbox Filter */}
                            <div className="flex h-10 items-center space-x-2 px-2">
                                <Checkbox
                                    id="include_completed"
                                    checked={
                                        filters.include_completed === 'true' ||
                                        filters.include_completed === true
                                    }
                                    onCheckedChange={(checked) =>
                                        handleFilterChange(
                                            checked ? 'true' : 'false',
                                            'include_completed',
                                        )
                                    }
                                />
                                <label
                                    htmlFor="include_completed"
                                    className="cursor-pointer text-sm leading-none font-medium text-muted-foreground transition-colors select-none hover:text-foreground"
                                >
                                    Include Completed
                                </label>
                            </div>

                            {/* Clear Button */}
                            <div className="flex flex-col gap-1.5">
                                <div className="h-[15px]" />
                                <Button
                                    variant="ghost"
                                    onClick={clearFilters}
                                    className="h-10 px-3 text-sm text-muted-foreground transition-colors hover:text-destructive"
                                >
                                    <X className="mr-1.5 h-4 w-4" />
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </div>

                    <DataTable columns={columns} pagination={sublimations} />
                </div>
            </div>

            {isDialogOpen && (
                <SublimationDialog
                    statuses={statuses}
                    open={isDialogOpen}
                    setOpen={setIsDialogOpen}
                    branches={branches}
                    users={users}
                    availableTags={availableTags}
                    sublimation={selectedSublimation}
                />
            )}

            {isGalleryOpen && gallerySublimation && (
                <Dialog open={isGalleryOpen} onOpenChange={setIsGalleryOpen}>
                    <DialogContent className="flex h-[95vh] !max-h-[95vh] !w-[95vw] !max-w-[95vw] flex-col overflow-hidden border-zinc-800 bg-white/95 p-6">
                        <DialogHeader className="flex-none">
                            <DialogTitle className="text-3xl font-bold text-black">
                                Sublimation Gallery
                            </DialogTitle>
                            <DialogDescription className="text-black">
                                Manage images for{' '}
                                {gallerySublimation.customer?.full_name ||
                                    'Customer'}
                            </DialogDescription>
                        </DialogHeader>

                        {/* Gallery Container */}
                        <div className="custom-scrollbar mt-6 flex-1 overflow-y-auto pr-2">
                            <SublimationGallery
                                sublimationId={gallerySublimation.id}
                                onOpenZoomedImage={(image) =>
                                    setZoomedImage(image)
                                }
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}

            {/* Zoom Modal */}
            <Dialog
                open={!!zoomedImage}
                onOpenChange={(open) => !open && setZoomedImage(null)}
            >
                {/* FIXED "BIGGER" SIZE:
      - Used !w-[98vw] and !h-[95vh] to force it to fill the screen.
      - bg-white for uniform color behind the image.
      - flex items-center justify-center to center the "Actual Size" image in the large space.
    */}
                <DialogContent className="custom-scrollbar fixed top-1/2 left-1/2 h-[95vh] !max-h-[95vh] !w-[98vw] -translate-x-1/2 -translate-y-1/2 overflow-auto rounded-xl border border-zinc-200 bg-white p-0 shadow-2xl sm:!max-w-[98vw] [&>button]:fixed [&>button]:top-6 [&>button]:right-6 [&>button]:z-[130] [&>button]:flex [&>button]:h-8 [&>button]:w-8 [&>button]:items-center [&>button]:justify-center [&>button]:rounded-full [&>button]:bg-zinc-900 [&>button]:text-white [&>button]:opacity-100 [&>button]:transition-all [&>button]:hover:bg-black">
                    <DialogTitle className="sr-only">Zoomed Image</DialogTitle>
                    <DialogDescription className="sr-only">
                        Actual size view of the selected image.
                    </DialogDescription>

                    {zoomedImage && (
                        /* The wrapper div:
                           - min-h-full min-w-full ensures the white background fills the modal.
                           - flex items-center justify-center keeps the image centered if it's smaller than the screen.
                        */
                        <div className="relative flex min-h-full min-w-full items-center justify-center bg-white p-12">
                            <img
                                src={zoomedImage.url}
                                alt={zoomedImage.name}
                                /* Actual Size:
                                   - Removed 'max-h-full' so it stays at its true pixel size.
                                   - Added shadow-xl to separate the image from the white background if they are both white.
                                */
                                className="block h-auto w-auto min-w-[300px] border border-zinc-100 shadow-xl"
                            />

                            {/* DOWNLOAD BUTTON:
                   - Positioned 'fixed' relative to the viewport (bottom-right of the modal).
                   - Colors synced to the Close Button (Zinc-900).
                */}
                            <div className="fixed right-10 bottom-10 z-[120]">
                                <a
                                    href={zoomedImage.url}
                                    download={zoomedImage.name}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-2 rounded-full bg-zinc-900 px-6 py-3 text-sm font-bold text-white shadow-2xl transition-all hover:scale-105 hover:bg-black active:scale-95"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    >
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                        <polyline points="7 10 12 15 17 10" />
                                        <line x1="12" x2="12" y1="15" y2="3" />
                                    </svg>
                                    Download Actual Size
                                </a>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
            <AlertDialog
                open={!!deleteTarget}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Are you absolutely sure?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This action cannot be undone. This will permanently
                            delete this sublimation.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                if (deleteTarget) {
                                    deleteSublimation(deleteTarget);
                                }
                                setDeleteTarget(null);
                            }}
                        >
                            Continue
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <Dialog
                open={!!duplicateTarget}
                onOpenChange={(open) => {
                    if (!open) {
                        setDuplicateTarget(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Items to Existing Sublimation</DialogTitle>
                        <DialogDescription>
                            Create an additional batch tied to this order. Edit
                            the description, adjust categories, and set the
                            amount.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();

                            if (!duplicateTarget || duplicating) {
                                return;
                            }

                            const tagPayload = duplicateSelectedTagIds
                                .filter((id) => Number(duplicateTagQtys[id]) > 0)
                                .map((id) => ({
                                    id,
                                    quantity: Number(duplicateTagQtys[id]),
                                }));

                            if (tagPayload.length === 0) {
                                toast.error('Select at least one category', { position: 'top-center' });
                                return;
                            }

                            setDuplicating(true);
                            router.post(
                                route('sublimations.duplicate', duplicateTarget.id),
                                {
                                    description: duplicateDescription,
                                    tag_ids: tagPayload,
                                    amount_total: Number(duplicateAmount),
                                },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        toast.success('Additional items added.', { position: 'top-center' });
                                        setDuplicateTarget(null);
                                    },
                                    onError: (errors) => {
                                        toast.error(
                                            errors.description ?? errors['tag_ids'] ?? errors.amount_total ?? errors.message ?? 'Failed to add items.',
                                            { position: 'top-center' },
                                        );
                                    },
                                    onFinish: () => setDuplicating(false),
                                },
                            );
                        }}
                        className="space-y-4 pt-2"
                    >
                        <div className="space-y-1">
                            <label className="text-sm font-medium">
                                Description
                            </label>
                            <input
                                type="text"
                                value={duplicateDescription}
                                onChange={(e) => setDuplicateDescription(e.target.value)}
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                placeholder="e.g. Team Jersey (Addtl. 1)"
                                required
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Categories
                            </label>
                            <TagSelector
                                selectedTagIds={duplicateSelectedTagIds}
                                availableTags={availableTags}
                                quantities={duplicateQuantitiesMap}
                                onAdd={addDuplicateTag}
                                onRemove={removeDuplicateTag}
                                onQuantityChange={updateDuplicateTagQty}
                                layout="row"
                            />
                            <div className="flex justify-end pr-1 text-xs tabular-nums text-muted-foreground">
                                Total Qty: <span className="ml-1 font-medium text-foreground">{duplicateTotalQty}</span>
                            </div>
                        </div>

                        <div className="space-y-1">
                            <label className="text-sm font-medium">
                                Amount (PHP)
                            </label>
                            <input
                                type="number"
                                min={1}
                                step="0.01"
                                value={duplicateAmount}
                                onChange={(e) => setDuplicateAmount(e.target.value)}
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                placeholder="e.g. 500.00"
                                required
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDuplicateTarget(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    !duplicateDescription.trim() ||
                                    duplicateTotalQty < 1 ||
                                    !duplicateAmount ||
                                    Number(duplicateAmount) < 1 ||
                                    duplicating
                                }
                            >
                                {duplicating ? 'Adding…' : 'Add Items'}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
