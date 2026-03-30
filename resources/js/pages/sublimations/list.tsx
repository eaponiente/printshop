import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { isToday, isPast, addDays, isBefore, parseISO, format, differenceInDays } from 'date-fns';
import { ArrowUpDown, Filter, Pencil, Plus, Trash2, X } from 'lucide-react';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SublimationDialog from '@/pages/sublimations/sublimation-dialog';
import { TagCell } from '@/pages/sublimations/tag-cell';
import type { BreadcrumbItem } from '@/types';
import type { Branch } from '@/types/branches';
import type { PaginatedResponse } from '@/types/pagination';
import type { Tag } from '@/types/settings';
import type { Sublimation } from '@/types/sublimations';
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
}

export default function SublimationIndex({
    sublimations,
    branches,
    filters,
    availableTags,
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
        type: 'mode' | 'date' | 'status' | 'branch_id',
    ) => {
        const params = { ...filters };

        if (type === 'branch_id') {
            params.branch_id = value;
        }

        router.get(`/sublimations`, params, {
            preserveState: true,
            replace: true,
        });
    };

    const toggleTagFilter = (tagId: string) => {
        const currentTags = filters.tags ? String(filters.tags).split(',') : [];
        const newTags = currentTags.includes(tagId)
            ? currentTags.filter((id: string) => id !== tagId)
            : [...currentTags, tagId];

        router.get(
            `/sublimations`,
            {
                ...filters,
                tags: newTags.length > 0 ? newTags.join(',') : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true, // This ensures the UI doesn't "jump" or lose focus
                replace: true,
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
        },
        {
            accessorKey: 'tags',
            header: 'Tags',
            cell: ({ row }) => (
                <TagCell sublimation={row.original} allTags={availableTags} />
            ),
        },
        // ... inside your columns array
        {
            accessorKey: 'due_at',
            header: 'Due'
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }: CellContext<any, any> ) => {
                const status = row.original.status.toLowerCase();

                // Define styles for each status
                const statusConfig = {
                    active: "bg-green-100 text-green-700 hover:bg-green-200 border-green-200",
                    pending: "bg-yellow-100 text-yellow-700 hover:bg-yellow-200 border-yellow-200",
                    finished: "bg-blue-100 text-blue-700 hover:bg-blue-200 border-blue-200",
                    released: "bg-red-100 text-red-700 hover:bg-red-200 border-red-200",
                };

                // Fallback style if status doesn't match
                const badgeStyle = statusConfig[status as keyof typeof statusConfig] || "bg-gray-100 text-gray-700";

                return (
                    <Badge className={`capitalize font-medium shadow-none border ${badgeStyle}`}>
                        {status}
                    </Badge>
                );
            },
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
                                <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                                <AlertDialogDescription>
                                    This action cannot be undone. This will permanently delete this
                                    sublimation.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction onClick={() => deleteSublimation(row.original)}>
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
                                <label className="ml-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                    Branch
                                </label>
                                <Select
                                    value={filters.branch_id || 'all'}
                                    onValueChange={(v) => handleFilterChange(v, 'branch_id')}
                                >
                                    <SelectTrigger className="h-10 w-[160px] bg-white text-sm">
                                        <SelectValue placeholder="All Branch" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Branch</SelectItem>
                                        {branches.map((branch) => (
                                            <SelectItem key={branch.id} value={String(branch.id)}>
                                                {branch.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Tags Filter */}
                            <div className="flex flex-col gap-1.5">
                                <label className="ml-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                                    Tags
                                </label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="h-10 border-dashed bg-white px-3 text-sm font-normal"
                                        >
                                            <Filter className="mr-2 h-3.5 w-3.5" />
                                            Filter Tags
                                            {filters.tags && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2 h-5 px-1.5 text-[10px] font-medium"
                                                >
                                                    {filters.tags.split(',').length}
                                                </Badge>
                                            )}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-[200px] p-2" align="start">
                                        <div className="flex flex-col gap-2">
                                            {availableTags.map((tag) => {
                                                // 1. Ensure we are doing a string-to-string comparison
                                                // 2. Use !! to force a boolean type
                                                const tagIdStr = String(tag.id);
                                                const currentTagsArray = filters.tags ? String(filters.tags).split(',') : [];
                                                const isSelected = currentTagsArray.includes(tagIdStr);

                                                return (
                                                    <div
                                                        key={tag.id}
                                                        className="flex items-center space-x-2 p-1 hover:bg-accent rounded"
                                                    >
                                                        <Checkbox
                                                            id={`tag-${tag.id}`}
                                                            // Force the checkbox to follow the prop strictly
                                                            checked={isSelected}
                                                            onCheckedChange={() => {
                                                                toggleTagFilter(tagIdStr);
                                                            }}
                                                        />
                                                        <label
                                                            htmlFor={`tag-${tag.id}`}
                                                            className="text-sm font-medium leading-none cursor-pointer flex-1"
                                                        >
                                                            {tag.name}
                                                        </label>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </PopoverContent>
                                </Popover>
                            </div>

                            {/* Clear Button */}
                            <div className="flex flex-col gap-1.5">
                                <div className="h-[15px]" />
                                <Button
                                    variant="ghost"
                                    onClick={clearFilters}
                                    className="h-10 px-3 text-sm text-muted-foreground hover:text-destructive transition-colors"
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
