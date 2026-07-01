import { Head, router, usePage } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { MapPin, Search } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Attendance Geo', href: '#' },
];

type GeoTimeLog = {
    id: number;
    type: string;
    timestamp: string;
    latitude: number | null;
    longitude: number | null;
    accuracy_meters: number | null;
    note: string | null;
    distance: number | null;
    proximity: 'within' | 'outside' | 'no_location' | 'no_branch_coords' | null;
    employee: {
        id: number;
        first_name: string;
        last_name: string;
        branch_id: number;
        branch: { id: number; name: string };
    };
};

type BranchOption = {
    id: number;
    name: string;
};

type Props = {
    timeLogs: PaginatedResponse<GeoTimeLog>;
    branches: BranchOption[];
    selectedBranch: number | null;
    dateFrom: string;
    dateTo: string;
};

const proximityBadge = (p: string | null) => {
    if (p === 'within') {
return 'text-green-600';
}

    if (p === 'outside') {
return 'text-amber-600';
}

    return 'text-muted-foreground';
};

const proximityLabel = (p: string | null) => {
    if (p === 'within') {
return 'Within radius';
}

    if (p === 'outside') {
return 'Outside radius';
}

    if (p === 'no_location') {
return 'No location';
}

    if (p === 'no_branch_coords') {
return 'No branch coords';
}

    return '—';
};

export default function AttendanceGeo({
    timeLogs,
    branches,
    selectedBranch,
    dateFrom,
    dateTo,
}: Props) {
    const { auth } = usePage().props as any;
    const isSuperAdmin = auth?.user?.role === 'superadmin';

    const [branchId, setBranchId] = useState<string>(
        selectedBranch ? String(selectedBranch) : '',
    );
    const [fromDate, setFromDate] = useState(dateFrom);
    const [toDate, setToDate] = useState(dateTo);
    const [hasSearched, setHasSearched] = useState(!!selectedBranch);

    const handleSearch = () => {
        if (!branchId) {
return;
}

        setHasSearched(true);
        router.get(
            '/payroll/attendance-geo',
            { branch_id: branchId, date_from: fromDate, date_to: toDate },
            { preserveState: true },
        );
    };

    const columns: ColumnDef<GeoTimeLog, any>[] = [
        {
            accessorKey: 'timestamp',
            header: 'Date',
            cell: ({ row }: CellContext<GeoTimeLog, any>) => {
                const d = new Date(row.original.timestamp);

                return (
                    <span className="font-mono text-xs">
                        {d.toLocaleDateString('en-PH', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                        })}
                    </span>
                );
            },
        },
        {
            accessorKey: 'employee',
            header: 'Employee',
            cell: ({ row }: CellContext<GeoTimeLog, any>) => (
                <span className="text-xs font-medium">
                    {row.original.employee?.last_name},{' '}
                    {row.original.employee?.first_name}
                </span>
            ),
        },
        {
            accessorKey: 'branch',
            header: 'Branch',
            cell: ({ row }: CellContext<GeoTimeLog, any>) => (
                <span className="text-xs text-muted-foreground">
                    {row.original.employee?.branch?.name ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }: CellContext<GeoTimeLog, any>) => {
                const t = row.original.type;

                return (
                    <span
                        className={`inline-block rounded-full border px-2 py-0.5 text-[10px] font-medium ${t === 'in' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-purple-200 bg-purple-50 text-purple-700'}`}
                    >
                        {t === 'in' ? 'IN' : 'OUT'}
                    </span>
                );
            },
        },
        {
            accessorKey: 'time',
            header: 'Time',
            cell: ({ row }: CellContext<GeoTimeLog, any>) => (
                <span className="font-mono text-xs">
                    {new Date(row.original.timestamp).toLocaleTimeString(
                        'en-PH',
                        {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false,
                        },
                    )}
                </span>
            ),
        },
        {
            accessorKey: 'distance',
            header: 'Distance',
            cell: ({ row }: CellContext<GeoTimeLog, any>) => {
                const d = row.original.distance;
                const p = row.original.proximity;

                if (d === null) {
                    return (
                        <span className="text-xs text-muted-foreground">—</span>
                    );
                }

                return (
                    <span
                        className={`font-mono text-xs font-medium ${proximityBadge(p)}`}
                    >
                        {d}m{' '}
                        {p === 'within' ? '✅' : p === 'outside' ? '⚠️' : ''}
                    </span>
                );
            },
        },
        {
            accessorKey: 'proximity',
            header: 'Status',
            cell: ({ row }: CellContext<GeoTimeLog, any>) => {
                const p = row.original.proximity;

                return (
                    <span
                        className={`inline-block rounded-full border px-2 py-0.5 text-[10px] font-medium capitalize ${proximityBadge(p)} ${p === 'within' ? 'border-green-200 bg-green-50' : p === 'outside' ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50'}`}
                    >
                        {proximityLabel(p)}
                    </span>
                );
            },
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attendance Geo" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">
                        Attendance Geolocation
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        View punch distances from branch office coordinates.
                    </p>
                </div>

                {isSuperAdmin && (
                    <div className="flex flex-wrap items-end gap-3 rounded-md border border-sidebar-border bg-sidebar p-4">
                        <div className="space-y-1">
                            <Label className="text-xs">Branch *</Label>
                            <Select
                                value={branchId}
                                onValueChange={setBranchId}
                            >
                                <SelectTrigger className="h-9 w-44 text-xs">
                                    <SelectValue placeholder="Select branch" />
                                </SelectTrigger>
                                <SelectContent>
                                    {branches.map((b) => (
                                        <SelectItem
                                            key={b.id}
                                            value={String(b.id)}
                                        >
                                            {b.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">From</Label>
                            <Input
                                type="date"
                                value={fromDate}
                                onChange={(e) => setFromDate(e.target.value)}
                                className="h-9 w-40 text-xs"
                            />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">To</Label>
                            <Input
                                type="date"
                                value={toDate}
                                onChange={(e) => setToDate(e.target.value)}
                                className="h-9 w-40 text-xs"
                            />
                        </div>
                        <Button
                            size="sm"
                            className="h-9"
                            disabled={!branchId}
                            onClick={handleSearch}
                        >
                            <Search className="mr-1.5 h-3.5 w-3.5" />
                            View
                        </Button>
                    </div>
                )}

                {!hasSearched && isSuperAdmin && (
                    <div className="flex flex-col items-center justify-center rounded-md border border-sidebar-border bg-sidebar p-12 text-center">
                        <MapPin className="mb-3 h-10 w-10 text-muted-foreground" />
                        <p className="text-sm font-medium text-muted-foreground">
                            Select a branch to view attendance distances
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Choose a branch above and set a date range to see
                            punch locations.
                        </p>
                    </div>
                )}

                {!hasSearched && !isSuperAdmin && (
                    <div className="flex flex-col items-center justify-center rounded-md border border-sidebar-border bg-sidebar p-12 text-center">
                        <MapPin className="mb-3 h-10 w-10 text-muted-foreground" />
                        <p className="text-sm font-medium text-muted-foreground">
                            No access
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            This page is only accessible by superadmin.
                        </p>
                    </div>
                )}

                {hasSearched && (
                    <div className="rounded-md border border-sidebar-border bg-sidebar">
                        <DataTable columns={columns} pagination={timeLogs} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
