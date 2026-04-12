import { Head, Link, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { differenceInMinutes, parseISO } from 'date-fns';
import { ArrowUpDown, Pencil, Plus, Trash2, X } from 'lucide-react';
import React, { useState } from 'react';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { StatusCell } from '@/pages/sublimations/status-cell';
import SublimationDialog from '@/pages/sublimations/sublimation-dialog';
import type { BreadcrumbItem } from '@/types';
import type { Branch } from '@/types/branches';
import type { PaginatedResponse } from '@/types/pagination';
import type { Tag } from '@/types/settings';
import type { Sublimation, SublimationStatus } from '@/types/sublimations';
import type { User } from '@/types/user';
import { sortBy } from '@/utils/helpers';

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
}: SublimationIndexProps) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [selectedSublimation, setSelectedSublimation] = useState<Sublimation | null>(null);

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
            onSuccess: () => toast.success('Sublimation deleted', { position: 'top-center' }),
        });
    };

    const handleFilterChange = (
        value: string,
        type: 'date' | 'status' | 'branch_id' | 'include_completed' | 'user_id',
    ) => {
        const params = { ...filters };

        if (type === 'branch_id') {
            params.branch_id = value;
        } else if (type === 'status') {
            params.status = value;
        } else if (type === 'include_completed') {
            params.include_completed = value;
        } else if (type === 'user_id') {
            params.user_id = value;
        }

        router.get(`/sublimations`, params, {
            preserveState: true,
            replace: true,
        });
    };

    const completeTransaction = (sublimation: Sublimation) => {
        router.patch(
            route('sublimations.complete', sublimation.id),
            {},
            {
                onSuccess: () => toast.success('Transaction completed!'),
                preserveScroll: true,
            },
        );
    };

    const clearFilters = () => {
        router.get(route('sublimations.index'), {}, { replace: true });
    };

    const columns: ColumnDef<any>[] = [
        {
            accessorKey: 'customer.full_name',
            header: 'Customer',
            cell: ({ row }) => {
                const { transaction, customer, created_at } = row.original;

                console.log('Transaction:', row.original);

                // Logic: Is this record less than 10 minutes old?
                const isRecent =
                    differenceInMinutes(new Date(), parseISO(created_at)) < 10;

                return (
                    <div className="flex items-center gap-2">
                        {transaction ? (
                            <Link
                                href={route('sales.index', {
                                    search: transaction.invoice_number,
                                    mode: 'yearly',
                                })}
                                className={`font-medium ${isRecent ? 'text-green-700' : 'text-indigo-600'} hover:underline`}
                            >
                                {customer.full_name}
                            </Link>
                        ) : (
                            <span className="font-medium text-muted-foreground">
                                {customer.full_name}
                            </span>
                        )}

                        {isRecent && (
                            <span className="relative flex h-2 w-2">
                                {/* Ping animation to grab attention */}
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                                <span className="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'notes',
            header: 'Notes',
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
                        Due At
                        <ArrowUpDown
                            className={`ml-2 h-4 w-4 ${isSorted ? 'text-primary' : 'text-muted-foreground/50'}`}
                        />
                    </Button>
                );
            },
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => <StatusCell item={row.original} statuses={statuses} />,
        },
        {
            accessorKey: 'branch.name',
            header: 'Branch',
        },
        {
            accessorKey: 'user.fullname',
            header: 'Assigned',
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<any, any>) => (
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
                                    permanently delete this sublimation.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() =>
                                        deleteSublimation(row.original)
                                    }
                                >
                                    Continue
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </>
            ),
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
                                    Branch
                                </label>
                                <Select
                                    value={filters.branch_id || 'all'}
                                    onValueChange={(v) =>
                                        handleFilterChange(v, 'branch_id')
                                    }
                                >
                                    <SelectTrigger className="h-10 w-[160px] bg-white text-sm">
                                        <SelectValue placeholder="All Branch" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Branch
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

                            {/* User Filter - Only shows when a specific branch is selected */}
                            {filters.branch_id &&
                                filters.branch_id !== 'all' && (
                                    <div className="flex flex-col gap-1.5">
                                        <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                            User / Staff
                                        </label>
                                        <Select
                                            value={filters.user_id || 'all'}
                                            onValueChange={(v) =>
                                                handleFilterChange(v, 'user_id')
                                            }
                                        >
                                            <SelectTrigger className="h-10 w-[160px] bg-white text-sm">
                                                <SelectValue placeholder="All Users" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    All Users
                                                </SelectItem>
                                                {users
                                                    .filter(
                                                        (user) =>
                                                            String(
                                                                user.branch_id,
                                                            ) ===
                                                            String(
                                                                filters.branch_id,
                                                            ),
                                                    )
                                                    .map((user: User) => (
                                                        <SelectItem
                                                            key={user.id}
                                                            value={String(
                                                                user.id,
                                                            )}
                                                        >
                                                            {user.fullname}
                                                        </SelectItem>
                                                    ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                            {/* Status Filter */}
                            <div className="flex flex-col gap-1.5">
                                <label className="ml-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                    Status
                                </label>
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(v) =>
                                        handleFilterChange(v, 'status')
                                    }
                                >
                                    <SelectTrigger className="h-10 w-[160px] bg-white text-sm">
                                        <SelectValue placeholder="All status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Status
                                        </SelectItem>
                                        {statuses.map(
                                            (status: SublimationStatus) => (
                                                <SelectItem
                                                    key={status.key}
                                                    value={status.key}
                                                >
                                                    {status.value}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>

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
                    sublimation={selectedSublimation}
                />
            )}
        </AppLayout>
    );
}
