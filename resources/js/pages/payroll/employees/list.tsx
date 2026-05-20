import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Trash2, Eye } from 'lucide-react';
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
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { EmployeesList } from '@/types/employee';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Employees', href: '/payroll/employees' },
];

const statusBadge = (status: string) => {
    const map: Record<string, string> = {
        active: 'bg-green-100 text-green-700 border-green-200',
        resigned: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        terminated: 'bg-red-100 text-red-700 border-red-200',
    };

    return map[status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
};

const positionBadge = (position: string) => {
    const map: Record<string, string> = {
        regular: 'bg-blue-100 text-blue-700 border-blue-200',
        contractual: 'bg-purple-100 text-purple-700 border-purple-200',
        project_based: 'bg-amber-100 text-amber-700 border-amber-200',
    };

    return map[position] ?? 'bg-gray-100 text-gray-700 border-gray-200';
};

export default function EmployeeIndex({
    employees,
    filterColumns,
    filters,
    statuses,
    positions,
}: EmployeesList) {
    const columns: ColumnDef<unknown, any>[] = [
        {
            accessorKey: 'employee_number',
            header: 'Emp #',
            cell: ({ row }: CellContext<any, any>) => (
                <span className="font-mono text-xs font-medium">
                    {row.original.employee_number}
                </span>
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
            accessorKey: 'position',
            header: 'Position',
            cell: ({ row }: CellContext<any, any>) => (
                <span
                    className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium ${positionBadge(row.original.position)}`}
                >
                    {row.original.position === 'project_based'
                        ? 'Project-Based'
                        : row.original.position.charAt(0).toUpperCase() +
                          row.original.position.slice(1)}
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
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button variant="ghost" size="sm" title="Delete">
                                <Trash2 className="h-4 w-4 text-red-500" />
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Delete {row.original.full_name}?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    This action cannot be undone. The employee
                                    record will be permanently deleted.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() =>
                                        router.delete(
                                            `/payroll/employees/${row.original.id}`,
                                            {
                                                onSuccess: () =>
                                                    toast.success(
                                                        'Employee deleted.',
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
                </div>
            ),
        },
    ];

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
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
                    <Button
                        onClick={() => router.get('/payroll/employees/create')}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Add Employee
                    </Button>
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
            </div>
        </PayrollLayout>
    );
}
