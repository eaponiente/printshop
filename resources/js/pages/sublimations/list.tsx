import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { TagCell } from '@/pages/sublimations/tag-cell';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import type { Sublimation, Tag } from '@/types/settings';
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sublimations', href: '/sublimations' },
];

interface SublimationIndexProps {
    sublimations: PaginatedResponse<Sublimation>; // Use the generic here
    availableTags: Tag[]; // Use the generic here
}

export default function SublimationIndex({ sublimations, availableTags }: SublimationIndexProps) {

// Pass to columns
    const columns: ColumnDef<any>[] = [
        {
            accessorKey: "id",
            header: "Order #",
        },
        {
            accessorKey: "branch.name",
            header: "Branch",
        },
        {
            accessorKey: "tags",
            header: "Tags",
            cell: ({ row }) => (
                <TagCell
                    sublimation={row.original}
                    allTags={availableTags}
                />
            ),
        },
        {
            accessorKey: "user.fullname",
            header: "Staff",
        }
    ]

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tags" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Sublimations</h1>
                        <p className="text-sm text-muted-foreground">Manage your sublimation.</p>
                    </div>

                    <Button onClick={() => console.log('create sublimation')}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add sublimation
                    </Button>
                </div>

                <DataTable columns={columns} pagination={sublimations} />
            </div>

        </AppLayout>
    );
}
