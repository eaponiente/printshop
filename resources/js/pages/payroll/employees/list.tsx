import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import {
    Ban,
    Eye,
    Link,
    Pencil,
    Plus,
    RefreshCw,
    Unlink,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { DataTable } from '@/components/data-table';
import { EmployeeFilter } from '@/components/shared/employee-filter';
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
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EmployeeSchedule, EmployeesList } from '@/types/employee';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Employees', href: '/payroll/employees' },
];

const statusBadge = (status: string) => {
    const map: Record<string, string> = {
        active: 'bg-green-100 text-green-700 border-green-200',
        inactive: 'bg-gray-100 text-gray-700 border-gray-200',
        resigned: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        terminated: 'bg-red-100 text-red-700 border-red-200',
    };

    return map[status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
};

const positionBadge = (position: string) => {
    const map: Record<string, string> = {
        regular: 'bg-blue-100 text-blue-700 border-blue-200',
        probation: 'bg-purple-100 text-purple-700 border-purple-200',
    };

    return map[position] ?? 'bg-gray-100 text-gray-700 border-gray-200';
};

const dayLabels: Record<number, string> = {
    0: 'Sunday',
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday',
};

const formatScheduleTooltip = (
    schedule: EmployeeSchedule | null | undefined,
) => {
    if (!schedule) {
        return 'No active schedule';
    }

    const restLabels =
        (schedule.rest_days ?? [])
            .map((d: number) => dayLabels[d] ?? String(d))
            .join(', ') || 'None';

    const effective = schedule.effective_to
        ? `${schedule.effective_from} → ${schedule.effective_to}`
        : `Since ${schedule.effective_from}`;

    return `Schedule: ${schedule.start_time} – ${schedule.end_time}
Unpaid tail: ${schedule.unpaid_tail_minutes} min
Rest days: ${restLabels}
${effective}`;
};

export default function EmployeeIndex({
    employees,
    filterColumns,
    filters,
    statuses,
    positions,
    unlinkedUsers = [],
    isSuperAdmin,
}: EmployeesList & {
    unlinkedUsers?: Array<{
        id: number;
        first_name: string;
        last_name: string;
        username: string;
        branch_id: number;
    }>;
}) {
    const [linkOpen, setLinkOpen] = useState(false);
    const [selectedEmp, setSelectedEmp] = useState<any>(null);

    const linkUser = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (!selectedEmp) {
            return;
        }

        const fd = new FormData(e.currentTarget);
        router.post(`/payroll/employees/${selectedEmp.id}/link-user`, fd, {
            onSuccess: () => {
                setLinkOpen(false);
                toast.success('User linked.');
            },
        });
    };

    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'employee_number',
            header: 'Emp #',
            cell: ({ row }: CellContext<any, any>) => (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span className="cursor-help font-mono text-xs font-medium">
                            {row.original.employee_number}
                        </span>
                    </TooltipTrigger>
                    <TooltipContent className="max-w-xs text-xs whitespace-pre-line">
                        {formatScheduleTooltip(row.original.active_schedule)}
                    </TooltipContent>
                </Tooltip>
            ),
        },
        {
            accessorKey: 'full_name',
            header: 'Name',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="font-medium">{row.original.full_name}</span>
            ),
        },
        {
            accessorKey: 'username',
            header: 'Username',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="font-medium">
                    {row.original.user.username}
                </span>
            ),
        },
        {
            accessorKey: 'position',
            header: 'Position',
            cell: ({ row }: CellContext<any, any>) => (
                <span
                    className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${positionBadge(row.original.position)}`}
                >
                    {row.original.position}
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
            accessorKey: 'user',
            header: 'User',
            cell: ({ row }: CellContext<any, any>) => {
                const user = row.original.user;

                return user ? (
                    <div className="flex items-center gap-1">
                        <span className="text-xs font-medium">
                            {user.username}
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-5 w-5 p-0"
                            title="Unlink"
                            onClick={() =>
                                router.post(
                                    `/payroll/employees/${row.original.id}/unlink-user`,
                                    {},
                                    {
                                        onSuccess: () =>
                                            toast.success('User unlinked.'),
                                    },
                                )
                            }
                        >
                            <Unlink className="h-3 w-3 text-red-500" />
                        </Button>
                    </div>
                ) : (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-7 text-xs text-muted-foreground"
                        onClick={() => {
                            setSelectedEmp(row.original);
                            setLinkOpen(true);
                        }}
                    >
                        <Link className="mr-1 h-3 w-3" />
                        Link
                    </Button>
                );
            },
        },
        {
            accessorKey: 'current_daily_rate',
            header: 'Daily Rate',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="font-mono text-sm">
                    {formatCurrency(row.original.current_daily_rate)}
                </span>
            ),
        },
        {
            accessorKey: 'branch.name',
            header: 'Branch',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="text-muted-foreground">
                    {row.original.branch?.name ?? '--'}
                </span>
            ),
        },
        {
            header: 'Actions',
            cell: ({ row }: CellContext<any, any>) => (
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            router.get(`/payroll/employees/${row.original.id}`)
                        }
                        title="View"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            router.get(
                                `/payroll/employees/${row.original.id}/edit`,
                            )
                        }
                        title="Edit"
                    >
                        <Pencil className="h-4 w-4" />
                    </Button>
                    {row.original.status === 'active' && (
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    title="Deactivate"
                                >
                                    <Ban className="h-4 w-4 text-red-500" />
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Deactivate {row.original.full_name}?
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        This employee will be deactivated and
                                        will no longer be able to access the
                                        system. Their data will be preserved.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>
                                        Cancel
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        onClick={() =>
                                            router.post(
                                                `/payroll/employees/${row.original.id}/deactivate`,
                                                {},
                                                {
                                                    onSuccess: () =>
                                                        toast.success(
                                                            'Employee deactivated.',
                                                            {
                                                                position:
                                                                    'top-center',
                                                            },
                                                        ),
                                                    onError: (err: any) =>
                                                        toast.error(
                                                            err.message ??
                                                                'Deactivation failed.',
                                                            {
                                                                position:
                                                                    'top-center',
                                                            },
                                                        ),
                                                },
                                            )
                                        }
                                    >
                                        Deactivate
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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Employees" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Employee Management
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your payroll employees.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {isSuperAdmin && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        '/payroll/employees/sync-all',
                                        {},
                                        {
                                            onSuccess: (page: any) =>
                                                toast.success(
                                                    page.props.flash?.message ??
                                                        'Employees synced.',
                                                ),
                                            onError: (err: any) =>
                                                toast.error(
                                                    err.message ??
                                                        'Sync failed.',
                                                ),
                                        },
                                    )
                                }
                            >
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Sync Employees
                            </Button>
                        )}
                        <Button
                            onClick={() =>
                                router.get('/payroll/employees/create')
                            }
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Employee
                        </Button>
                    </div>
                </div>

                <EmployeeFilter
                    columns={filterColumns}
                    filters={filters}
                    statuses={statuses}
                    positions={positions}
                    url="/payroll/employees"
                />

                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={employees} />
                </div>

                {(unlinkedUsers ?? []).length > 0 && (
                    <div className="rounded-md border border-sidebar-border bg-sidebar p-4">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold text-muted-foreground">
                            <UserPlus className="h-4 w-4" />
                            Users Without Employee Records
                        </h2>
                        <div className="space-y-2">
                            {(unlinkedUsers ?? []).map((u: any) => (
                                <div
                                    key={u.id}
                                    className="flex items-center justify-between rounded border px-3 py-2 text-sm"
                                >
                                    <div className="flex items-center gap-3">
                                        <span className="font-medium">
                                            {u.first_name} {u.last_name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            @{u.username}
                                        </span>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                `/payroll/employees/sync-user/${u.id}`,
                                                {},
                                                {
                                                    onSuccess: () =>
                                                        toast.success(
                                                            'Employee created from user.',
                                                        ),
                                                },
                                            )
                                        }
                                    >
                                        <RefreshCw className="mr-1 h-3 w-3" />
                                        Sync to Employee
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <Dialog
                    open={linkOpen && !!selectedEmp}
                    onOpenChange={setLinkOpen}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                Link User to {selectedEmp?.full_name}
                            </DialogTitle>
                        </DialogHeader>
                        <form onSubmit={linkUser} className="space-y-3">
                            <div className="space-y-1">
                                <Label htmlFor="user_id">Select User</Label>
                                <NativeSelect
                                    id="user_id"
                                    name="user_id"
                                    required
                                >
                                    <NativeSelectOption value="">
                                        Choose a user...
                                    </NativeSelectOption>
                                    {(unlinkedUsers ?? [])
                                        .filter(
                                            (u: any) =>
                                                u.branch_id ===
                                                selectedEmp?.branch_id,
                                        )
                                        .map((u: any) => (
                                            <NativeSelectOption
                                                key={u.id}
                                                value={String(u.id)}
                                            >
                                                {u.first_name} {u.last_name} (@
                                                {u.username})
                                            </NativeSelectOption>
                                        ))}
                                </NativeSelect>
                            </div>
                            <Button type="submit" className="w-full">
                                Link User
                            </Button>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
