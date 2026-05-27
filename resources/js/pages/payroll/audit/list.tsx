import { Head } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { DataTable } from '@/components/data-table';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { AuditLogsList } from '@/types/audit-log';
import { toManilaTime } from '@/utils/dateHelper';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Audit Logs', href: '/payroll/audit-logs' },
];

const actionBadge = (action: string) => {
    const map: Record<string, string> = {
        created: 'bg-green-100 text-green-700 border-green-200',
        updated: 'bg-blue-100 text-blue-700 border-blue-200',
        deleted: 'bg-red-100 text-red-700 border-red-200',
        rehired: 'bg-amber-100 text-amber-700 border-amber-200',
    };

    return map[action] ?? 'bg-gray-100 text-gray-700 border-gray-200';
};

function formatChanges(
    before: Record<string, unknown> | null,
    after: Record<string, unknown> | null,
) {
    if (!before && !after) {
        return <span className="text-muted-foreground">—</span>;
    }

    if (!before && after) {
        return <span className="text-muted-foreground">Record created</span>;
    }

    const keys = [
        ...Object.keys(before ?? {}),
        ...Object.keys(after ?? {}),
    ].filter((k, i, arr) => arr.indexOf(k) === i);

    return (
        <div className="space-y-0.5">
            {keys.map((key) => {
                const label = key.replace(/_/g, ' ');
                const oldVal = formatValue(before?.[key]);
                const newVal = formatValue(after?.[key]);

                return (
                    <div
                        key={key}
                        className="flex items-baseline gap-1.5 text-xs"
                    >
                        <span className="font-medium text-foreground capitalize">
                            {label}:
                        </span>
                        <span className="text-red-500 line-through">
                            {oldVal}
                        </span>
                        <span className="text-muted-foreground">→</span>
                        <span className="text-green-600">{newVal}</span>
                    </div>
                );
            })}
        </div>
    );
}

function formatValue(val: unknown): string {
    if (val === null) {
        return '—';
    }

    if (val === true) {
        return 'Yes';
    }

    if (val === false) {
        return 'No';
    }

    return String(val);
}

export default function AuditLogIndex({ logs }: AuditLogsList) {
    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'created_at',
            header: 'Date',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-xs whitespace-nowrap text-muted-foreground">
                    {toManilaTime(
                        row.original.created_at,
                        'MMM DD, YYYY hh:mm A',
                    )}
                </span>
            ),
        },
        {
            accessorKey: 'user',
            header: 'User',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-sm">
                    {row.original.user?.fullname ?? 'System'}
                </span>
            ),
        },
        {
            accessorKey: 'branch',
            header: 'Branch',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-xs text-muted-foreground">
                    {row.original.branch?.name ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'action',
            header: 'Action',
            cell: ({ row }: CellContext<any, any>) => (
                <span
                    className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${actionBadge(row.original.action)}`}
                >
                    {row.original.action}
                </span>
            ),
        },
        {
            accessorKey: 'model_type',
            header: 'Model',
            cell: ({ row }: CellContext<any, any>) => {
                const fqn: string = row.original.model_type ?? '';
                const name = fqn.split('\\').pop() ?? '';

                return (
                    <span className="text-xs text-muted-foreground">
                        {name} #{row.original.model_id}
                    </span>
                );
            },
        },
        {
            accessorKey: 'changes',
            header: 'Changes',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-xs">
                    {formatChanges(row.original.before, row.original.after)}
                </span>
            ),
        },
    ];

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Logs" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Audit Logs</h1>
                    <p className="text-sm text-muted-foreground">
                        Immutable record of all payroll actions.
                    </p>
                </div>
                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={logs} />
                </div>
            </div>
        </PayrollLayout>
    );
}
