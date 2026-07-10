import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Eye, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
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
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Payroll Periods', href: '/payroll/periods' },
];

type Period = {
    id: number;
    branch: { id: number; name: string };
    period_start: string;
    period_end: string;
    status: 'draft' | 'approved' | 'paid' | 'voided';
    approved_at: string | null;
};

type Props = {
    periods: PaginatedResponse<Period>;
    canGenerate: boolean;
    isSuperAdmin: boolean;
    branches: Array<{ id: number; name: string }>;
};

const statusBadge = (status: string) => {
    const map: Record<string, string> = {
        draft: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        approved: 'bg-green-100 text-green-700 border-green-200',
        paid: 'bg-blue-100 text-blue-700 border-blue-200',
        voided: 'bg-red-100 text-red-700 border-red-200',
    };

    return map[status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
};

export default function PayrollPeriodsIndex({
    periods,
    canGenerate,
    isSuperAdmin,
    branches,
}: Props) {
    const columns: ColumnDef<Period>[] = [
        {
            accessorKey: 'branch',
            header: 'Branch',
            cell: ({ row }: CellContext<Period, any>) => (
                <span>{row.original.branch?.name}</span>
            ),
        },
        {
            accessorKey: 'period_start',
            header: 'Start',
            cell: ({ row }: CellContext<Period, any>) => (
                <span className="font-mono text-sm">
                    {row.original.period_start}
                </span>
            ),
        },
        {
            accessorKey: 'period_end',
            header: 'End',
            cell: ({ row }: CellContext<Period, any>) => (
                <span className="font-mono text-sm">
                    {row.original.period_end}
                </span>
            ),
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }: CellContext<Period, any>) => (
                <span
                    className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${statusBadge(row.original.status)}`}
                >
                    {row.original.status}
                </span>
            ),
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<Period, any>) => (
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            router.get(`/payroll/periods/${row.original.id}`)
                        }
                        title="View"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    {canGenerate && row.original.status === 'draft' && (
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    title="Delete"
                                >
                                    <Trash2 className="h-4 w-4 text-red-500" />
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Delete draft period?
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        This will delete the draft payroll
                                        period and unlock all attendance sheets.
                                        This cannot be undone.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>
                                        Cancel
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        onClick={() =>
                                            router.delete(
                                                `/payroll/periods/${row.original.id}`,
                                                {
                                                    onSuccess: () =>
                                                        toast.success(
                                                            'Period deleted.',
                                                            {
                                                                position:
                                                                    'top-center',
                                                            },
                                                        ),
                                                    onError: (err: any) =>
                                                        toast.error(
                                                            err.message ??
                                                                'Deletion failed.',
                                                            {
                                                                position:
                                                                    'top-center',
                                                            },
                                                        ),
                                                },
                                            )
                                        }
                                    >
                                        Delete
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    )}
                </div>
            ),
        },
    ];

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll Periods" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Payroll Periods
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Weekly Mon–Sat payroll periods.
                        </p>
                    </div>
                    {canGenerate && (
                        <GenerateDialog
                            isSuperAdmin={isSuperAdmin}
                            branches={branches}
                        >
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Generate Period
                            </Button>
                        </GenerateDialog>
                    )}
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={periods} />
                </div>
            </div>
        </PayrollLayout>
    );
}

function GenerateDialog({
    children,
    isSuperAdmin,
    branches,
}: {
    children: React.ReactNode;
    isSuperAdmin: boolean;
    branches: Array<{ id: number; name: string }>;
}) {
    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
        router.post('/payroll/periods/generate', form, {
            onSuccess: () => toast.success('Period generated.'),
            onError: () => toast.error('Failed to generate.'),
        });
    };

    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Generate Payroll Period</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {isSuperAdmin && (
                        <div className="space-y-2">
                            <Label htmlFor="branch_id">Branch</Label>
                            <NativeSelect
                                id="branch_id"
                                name="branch_id"
                                required
                            >
                                <NativeSelectOption value="">
                                    Select branch
                                </NativeSelectOption>
                                {branches.map((b) => (
                                    <NativeSelectOption
                                        key={b.id}
                                        value={String(b.id)}
                                    >
                                        {b.name}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        </div>
                    )}
                    <div className="space-y-2">
                        <Label htmlFor="period_start">Period Start (Mon)</Label>
                        <Input
                            id="period_start"
                            name="period_start"
                            type="date"
                            required
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="period_end">Period End (Sat)</Label>
                        <Input
                            id="period_end"
                            name="period_end"
                            type="date"
                            required
                        />
                    </div>
                    <Button type="submit" className="w-full">
                        Generate
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
