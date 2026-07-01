import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { DataTable } from '@/components/data-table';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    { title: 'Holidays', href: '/payroll/holidays' },
];

type Holiday = {
    id: number;
    name: string;
    date: string;
    type: 'regular' | 'special';
    recurring: boolean;
};

type Props = {
    holidays: PaginatedResponse<Holiday>;
    types: Array<{ key: string; value: string }>;
};

const typeBadge = (type: string) => {
    return type === 'regular'
        ? 'bg-red-100 text-red-700 border-red-200'
        : 'bg-amber-100 text-amber-700 border-amber-200';
};

export default function HolidayIndex({ holidays, types }: Props) {
    const columns: ColumnDef<Holiday>[] = [
        {
            accessorKey: 'name',
            header: 'Name',
            cell: ({ row }: CellContext<Holiday, any>) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            accessorKey: 'date',
            header: 'Date',
            cell: ({ row }: CellContext<Holiday, any>) => (
                <span className="font-mono text-sm">{row.original.date}</span>
            ),
        },
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }: CellContext<Holiday, any>) => (
                <span
                    className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${typeBadge(row.original.type)}`}
                >
                    {row.original.type === 'regular' ? 'Regular' : 'Special'}
                </span>
            ),
        },
        {
            accessorKey: 'recurring',
            header: 'Recurring',
            cell: ({ row }: CellContext<Holiday, any>) => (
                <span className="text-sm">
                    {row.original.recurring ? 'Yes' : 'No'}
                </span>
            ),
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<Holiday, any>) => (
                <div className="flex items-center gap-1">
                    <HolidayDialog holiday={row.original} types={types}>
                        <Button variant="ghost" size="sm" title="Edit">
                            <Pencil className="h-4 w-4" />
                        </Button>
                    </HolidayDialog>
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button variant="ghost" size="sm" title="Delete">
                                <Trash2 className="h-4 w-4 text-red-500" />
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Delete {row.original.name}?
                                </AlertDialogTitle>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() =>
                                        router.delete(
                                            `/payroll/holidays/${row.original.id}`,
                                            {
                                                onSuccess: () =>
                                                    toast.success('Deleted.'),
                                                onError: () =>
                                                    toast.error('Failed.'),
                                            },
                                        )
                                    }
                                >
                                    Delete
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
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
                    <HolidayDialog types={types}>
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
        </AppLayout>
    );
}

function HolidayDialog({
    holiday,
    types,
    children,
}: {
    holiday?: Holiday;
    types: Props['types'];
    children: React.ReactNode;
}) {
    const isEdit = !!holiday;

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const form = e.currentTarget;
        const formData = new FormData(form);

        if (isEdit && holiday) {
            router.put(`/payroll/holidays/${holiday.id}`, formData, {
                onSuccess: () => toast.success('Updated.'),
                onError: () => toast.error('Failed.'),
            });
        } else {
            router.post('/payroll/holidays', formData, {
                onSuccess: () => toast.success('Added.'),
                onError: () => toast.error('Failed.'),
            });
        }
    };

    return (
        <Dialog>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'Edit Holiday' : 'Add Holiday'}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            defaultValue={holiday?.name ?? ''}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="date">Date</Label>
                        <Input
                            id="date"
                            name="date"
                            type="date"
                            required
                            defaultValue={holiday?.date ?? ''}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="type">Type</Label>
                        <Select
                            name="type"
                            defaultValue={holiday?.type ?? types[0]?.key}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((t) => (
                                    <SelectItem key={t.key} value={t.key}>
                                        {t.value}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex items-center gap-2">
                        <input type="hidden" name="recurring" value="0" />
                        <Checkbox
                            id="recurring"
                            name="recurring"
                            defaultChecked={holiday?.recurring ?? true}
                        />
                        <Label
                            htmlFor="recurring"
                            className="cursor-pointer text-sm"
                        >
                            Recurring (repeat every year)
                        </Label>
                    </div>
                    <Button type="submit" className="w-full">
                        {isEdit ? 'Update' : 'Add'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
