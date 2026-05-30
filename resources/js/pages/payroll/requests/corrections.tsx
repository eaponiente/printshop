import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Check, X } from 'lucide-react';
import { toast } from 'sonner';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';

type Props = { requests: PaginatedResponse<any> };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Correction Requests', href: '/payroll/correction-requests' },
];
const statusBadge = (s: string) =>
    ({
        approved: 'bg-green-100 text-green-700',
        denied: 'bg-red-100 text-red-700',
        pending: 'bg-yellow-100 text-yellow-700',
    })[s] ?? 'bg-gray-100';

export default function CorrectionRequests({ requests }: Props) {
    const columns: ColumnDef<any>[] = [
        {
            accessorKey: 'employee',
            header: 'Employee',
            cell: ({ row }: CellContext<any, any>) => (
                <span>
                    {row.original.employee?.last_name},{' '}
                    {row.original.employee?.first_name}
                </span>
            ),
        },
        {
            accessorKey: 'date',
            header: 'Date',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="font-mono text-sm">{row.original.date}</span>
            ),
        },
        {
            accessorKey: 'correction_type',
            header: 'Type',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-xs capitalize">
                    {row.original.correction_type?.replace(/_/g, ' ')}
                </span>
            ),
        },
        {
            accessorKey: 'reason',
            header: 'Reason',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-xs text-muted-foreground">
                    {row.original.reason}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }: CellContext<any, any>) => (
                <span
                    className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${statusBadge(row.original.status)}`}
                >
                    {row.original.status}
                </span>
            ),
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<any, any>) =>
                row.original.status === 'pending' ? (
                    <div className="flex gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    `/payroll/correction-requests/${row.original.id}/approve`,
                                    {},
                                    {
                                        onSuccess: () =>
                                            toast.success('Approved'),
                                    },
                                )
                            }
                        >
                            <Check className="h-4 w-4 text-green-600" />
                        </Button>
                        <DenyDialog id={row.original.id}>
                            <Button variant="ghost" size="sm">
                                <X className="h-4 w-4 text-red-500" />
                            </Button>
                        </DenyDialog>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                ),
        },
    ];

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Correction Requests" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">
                        Correction Requests
                    </h1>
                </div>
                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={requests} />
                </div>
            </div>
        </PayrollLayout>
    );
}

function DenyDialog({
    id,
    children,
}: {
    id: number;
    children: React.ReactNode;
}) {
    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);
        router.post(`/payroll/correction-requests/${id}/deny`, fd, {
            onSuccess: () => toast.success('Denied'),
        });
    };

    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Deny Correction</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-3">
                    <div className="space-y-1">
                        <Label htmlFor="denial_reason">Reason</Label>
                        <Input
                            id="denial_reason"
                            name="denial_reason"
                            required
                        />
                    </div>
                    <Button type="submit" className="w-full">
                        Deny
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
