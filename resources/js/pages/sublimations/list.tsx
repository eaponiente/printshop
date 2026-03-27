import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Filter, Plus, X } from 'lucide-react';
import React from 'react';
import { route } from 'ziggy-js';
import { DataTable } from '@/components/data-table';
import { Badge } from "@/components/ui/badge"
import { Button } from '@/components/ui/button';
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { TagCell } from '@/pages/sublimations/tag-cell';
import type { BreadcrumbItem } from '@/types';
import type { Branch } from '@/types/branches';
import type { PaginatedResponse } from '@/types/pagination';
import type { Tag } from '@/types/settings';
import type { Sublimation } from '@/types/sublimations';
import { sortBy } from '@/utils/helpers';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sublimations', href: '/sublimations' },
];

interface SublimationIndexProps {
    sublimations: PaginatedResponse<Sublimation>; // Use the generic here
    branches: Branch[];
    filters: any;
    availableTags: Tag[]; // Use the generic here
}

export default function SublimationIndex({
    sublimations,
    branches,
    filters,
    availableTags,
}: SublimationIndexProps) {
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
            ? currentTags.filter(id => id !== tagId)
            : [...currentTags, tagId];

        // Join with comma for the URL query string: ?tags=1,2,3
        router.get(`/sublimations`, {
            ...filters,
            tags: newTags.length > 0 ? newTags.join(',') : undefined
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        router.get(route('sublimations.index'), {}, { replace: true });
    };

    const columns: ColumnDef<any>[] = [
        {
            accessorKey: 'particular',
            header: 'Particular',
        },

        {
            accessorKey: 'tags',
            header: 'Tags',
            cell: ({ row }) => (
                <TagCell sublimation={row.original} allTags={availableTags} />
            ),
        },
        {
            accessorKey: 'due_at',
            header: () => {
                const isSorted = filters.sort_field === 'due_at';

                return (
                    <Button
                        variant="ghost"
                        // Pass the field, the current filters object, and the route
                        onClick={() => sortBy('due_at', filters, 'sublimations.index')}
                        className="hover:bg-transparent p-0"
                    >
                        Due Date
                        <ArrowUpDown className={`ml-2 h-4 w-4 ${isSorted ? "text-primary" : "text-muted-foreground/50"}`} />
                    </Button>
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
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tags" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Sublimations</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your sublimation.
                        </p>
                    </div>

                    <Button onClick={() => console.log('create sublimation')}>
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
                                        <Button variant="outline" className="h-10 border-dashed bg-white px-3 text-sm font-normal">
                                            <Filter className="mr-2 h-3.5 w-3.5" />
                                            Filter Tags
                                            {filters.tags && (
                                                <Badge variant="secondary" className="ml-2 h-5 px-1.5 text-[10px] font-medium">
                                                    {filters.tags.split(',').length}
                                                </Badge>
                                            )}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-[200px] p-2" align="start">
                                        <div className="flex flex-col gap-2">
                                            {availableTags.map((tag) => {
                                                const isSelected = filters.tags?.split(',').includes(String(tag.id));

                                                return (
                                                    <div key={tag.id} className="flex items-center space-x-2 p-1 hover:bg-accent rounded">
                                                        <Checkbox
                                                            id={`tag-${tag.id}`}
                                                            checked={isSelected}
                                                            onCheckedChange={() => toggleTagFilter(String(tag.id))}
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
                                    </PopoverContent>                                </Popover>
                            </div>

                            {/* Clear Button - Wrapped to match height of input containers */}
                            <div className="flex flex-col gap-1.5">
                                {/* Empty label or div to push the button down to the same level as inputs */}
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
        </AppLayout>
    );
}
