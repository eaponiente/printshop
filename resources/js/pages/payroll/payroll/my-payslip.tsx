import { Head, router } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Eye } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/pagination';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'My Payslips', href: '/payroll/my-payslip' },
];

type PayslipItem = {
    id: number;
    payroll_period_id: number;
    net_pay: number;
    payroll_period: {
        id: number;
        period_start: string;
        period_end: string;
        status: string;
    };
};

type Props = {
    payslips: PaginatedResponse<PayslipItem>;
};

const statusBadge = (status: string) => {
    // Voided periods are filtered out server-side, so no branch for them here.
    const map: Record<string, string> = {
        draft: 'bg-yellow-100 text-yellow-700',
        approved: 'bg-green-100 text-green-700',
        paid: 'bg-blue-100 text-blue-700',
    };

    return map[status] ?? 'bg-gray-100 text-gray-600';
};

export default function MyPayslips({ payslips }: Props) {
    const columns: ColumnDef<PayslipItem>[] = [
        {
            accessorKey: 'payroll_period',
            header: 'Payroll Period',
            cell: ({ row }: CellContext<PayslipItem, any>) => (
                <span className="font-mono text-sm">
                    {row.original.payroll_period?.period_start} –{' '}
                    {row.original.payroll_period?.period_end}
                </span>
            ),
        },
        {
            accessorKey: 'net_pay',
            header: 'Net Pay',
            cell: ({ row }: CellContext<PayslipItem, any>) => (
                <span className="font-medium tabular-nums">
                    {formatCurrency(row.original.net_pay)}
                </span>
            ),
        },
        {
            accessorKey: 'payroll_period.status',
            header: 'Status',
            cell: ({ row }: CellContext<PayslipItem, any>) => (
                <span
                    className={`inline-block rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${statusBadge(row.original.payroll_period?.status)}`}
                >
                    {row.original.payroll_period?.status}
                </span>
            ),
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }: CellContext<PayslipItem, any>) => (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() =>
                        router.get(
                            `/payroll/periods/${row.original.payroll_period_id}/payslip/${row.original.id}`,
                        )
                    }
                >
                    <Eye className="mr-1 h-4 w-4" />
                    View
                </Button>
            ),
        },
    ];

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="My Payslips" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">My Payslips</h1>
                </div>
                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <DataTable columns={columns} pagination={payslips} />
                </div>
            </div>
        </PayrollLayout>
    );
}
