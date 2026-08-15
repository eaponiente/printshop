import { Head } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { BranchesCell } from './components/branches-cell';
import { DeleteHolidayDialog } from './components/delete-holiday-dialog';
import { HolidayDialog } from './components/holiday-dialog';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Holidays', href: '/payroll/holidays' },
];

export type HolidayBranch = { id: number; name: string };

export type Holiday = {
    id: number;
    name: string;
    date: string;
    type: 'regular' | 'special';
    recurring: boolean;
    branches: HolidayBranch[];
};

export type Props = {
    holidays: PaginatedResponse<Holiday>;
    types: Array<{ key: string; value: string }>;
    branches: HolidayBranch[];
};

const typeBadge = (type: string) => {
    return type === 'regular'
        ? 'bg-red-100 text-red-700 border-red-200'
        : 'bg-amber-100 text-amber-700 border-amber-200';
};

export default function HolidayIndex({ holidays, types, branches }: Props) {
    // "Today" in Manila as an ISO date string (en-CA yields YYYY-MM-DD), so a
    // plain lexicographic compare against holiday.date flags past holidays.
    const todayManila = new Date().toLocaleDateString('en-CA', {
        timeZone: 'Asia/Manila',
    });
    const isPast = (date: string) => date < todayManila;
    // Display without the year, e.g. "August 21". Parsed as UTC so the stored
    // Y-m-d renders exactly, with no timezone drift.
    const formatMonthDay = (date: string) =>
        new Date(`${date}T00:00:00Z`).toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            timeZone: 'UTC',
        });

    const columns: ColumnDef<Holiday>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }: CellContext<Holiday, unknown>) => (
                <span
                    className={
                        isPast(row.original.date)
                            ? 'font-medium text-muted-foreground'
                            : 'font-medium'
                    }
                >
                    {row.original.name}
                </span>
            ),
        },
        {
            accessorKey: 'date',
            header: 'Date',
            cell: ({ row }: CellContext<Holiday, unknown>) => (
                <span className="flex items-center gap-2">
                    <span
                        className={`text-sm ${isPast(row.original.date) ? 'text-muted-foreground' : ''}`}
                    >
                        {formatMonthDay(row.original.date)}
                    </span>
                    {isPast(row.original.date) && (
                        <span className="rounded-full border border-border px-2 py-0.5 text-xs font-medium text-muted-foreground">
                            Passed
                        </span>
                    )}
                </span>
            ),
        },
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }: CellContext<Holiday, unknown>) => (
                <span
                    className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${typeBadge(row.original.type)}`}
                >
                    {row.original.type === 'regular' ? 'Regular' : 'Special'}
                </span>
            ),
        },
        {
            id: 'branches',
            header: 'Branches',
            cell: ({ row }: CellContext<Holiday, unknown>) => (
                <BranchesCell branches={row.original.branches} />
            ),
        },
        {
            accessorKey: 'recurring',
            header: 'Recurring',
            cell: ({ row }: CellContext<Holiday, unknown>) => (
                <span className="text-sm">
                    {row.original.recurring ? 'Yes' : 'No'}
                </span>
            ),
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<Holiday, unknown>) => (
                <div className="flex items-center gap-1">
                    <HolidayDialog
                        holiday={row.original}
                        types={types}
                        branches={branches}
                    >
                        <Button variant="ghost" size="sm" title="Edit">
                            <Pencil className="h-4 w-4" />
                        </Button>
                    </HolidayDialog>
                    <DeleteHolidayDialog holiday={row.original} />
                </div>
            ),
        },
    ];

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Holidays" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Holiday Management
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage regular and special non-working holidays.
                        </p>
                    </div>
                    <HolidayDialog types={types} branches={branches}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Holiday
                        </Button>
                    </HolidayDialog>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={holidays} />
                </div>
            </div>
        </PayrollLayout>
    );
}
