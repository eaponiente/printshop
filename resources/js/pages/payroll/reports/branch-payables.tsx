import { Head, router, usePage } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Reports', href: '/payroll/reports' },
    { title: 'Branch Payables', href: '#' },
];

type BranchResult = {
    branch: string;
    branch_id: number;
    sss_employer: number;
    philhealth_employer: number;
    pagibig_employer: number;
    deminimis: number;
    total_benefits: number;
};

type GrandTotal = {
    sss_employer: number;
    philhealth_employer: number;
    pagibig_employer: number;
    deminimis: number;
    total_benefits: number;
};

type Props = {
    branches: { id: number; name: string }[];
    results: BranchResult[];
    grand_total: GrandTotal;
    filters?: {
        date_from?: string;
        date_to?: string;
        branch_id?: string;
    };
};

const columns = [
    { key: 'branch' as const, label: 'Branch' },
    { key: 'sss_employer' as const, label: 'SSS Employer' },
    { key: 'philhealth_employer' as const, label: 'PhilHealth Employer' },
    { key: 'pagibig_employer' as const, label: 'Pag-IBIG Employer' },
    { key: 'deminimis' as const, label: 'Deminimis' },
    { key: 'total_benefits' as const, label: 'Total Benefits' },
];

export default function BranchPayables({
    branches,
    results,
    grand_total,
    filters,
}: Props) {
    const { auth } = usePage().props as any;
    const isSuperAdmin = auth?.user?.role === 'superadmin';

    const [dateFrom, setDateFrom] = useState(filters?.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters?.date_to ?? '');
    const [branchId, setBranchId] = useState(filters?.branch_id ?? '');

    if (!isSuperAdmin) {
        return (
            <PayrollLayout breadcrumbs={breadcrumbs}>
                <Head title="Branch Payables" />
                <div className="flex h-full items-center justify-center p-4">
                    <p className="text-sm text-muted-foreground">
                        You do not have permission to view this page.
                    </p>
                </div>
            </PayrollLayout>
        );
    }

    const handleView = () => {
        if (!dateFrom || !dateTo) return;
        const params: Record<string, string> = {
            date_from: dateFrom,
            date_to: dateTo,
        };
        if (branchId) {
            params.branch_id = branchId;
        }
        router.get('/payroll/reports/branch-payables', params, {
            preserveState: true,
            replace: true,
        });
    };

    const canView = dateFrom && dateTo;
    const hasResults = results.length > 0;

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Branch Payables" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Branch Payables</h1>
                    <p className="text-sm text-muted-foreground">
                        Total employer benefits per branch over a custom date
                        range.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Select a date range and optionally filter by branch.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="date_from">Date From</Label>
                                <Input
                                    id="date_from"
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) =>
                                        setDateFrom(e.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="date_to">Date To</Label>
                                <Input
                                    id="date_to"
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                />
                            </div>
                            <div className="min-w-[200px] space-y-2">
                                <Label>Branch</Label>
                                <Select
                                    value={branchId}
                                    onValueChange={setBranchId}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="All branches" />
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
                            <Button disabled={!canView} onClick={handleView}>
                                <Banknote className="mr-2 h-4 w-4" />
                                View Report
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {filters?.date_from && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Results</CardTitle>
                            <CardDescription>
                                {filters.date_from} to {filters.date_to}
                                {filters.branch_id
                                    ? ` — ${branches.find((b) => String(b.id) === filters.branch_id)?.name ?? 'Unknown branch'}`
                                    : ' — All branches'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!hasResults ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">
                                    No payroll periods found in this date range.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b">
                                                {columns.map((col) => (
                                                    <th
                                                        key={col.key}
                                                        className={`px-4 py-2 text-left font-medium ${col.key === 'branch' ? '' : 'text-right'}`}
                                                    >
                                                        {col.label}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {results.map((row) => (
                                                <tr
                                                    key={row.branch_id}
                                                    className="border-b"
                                                >
                                                    {columns.map((col) => (
                                                        <td
                                                            key={col.key}
                                                            className={`px-4 py-2 ${col.key === 'branch' ? 'font-medium' : 'text-right tabular-nums'}`}
                                                        >
                                                            {col.key ===
                                                            'branch'
                                                                ? row[col.key]
                                                                : formatCurrency(
                                                                      row[
                                                                          col
                                                                              .key
                                                                      ],
                                                                  )}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}

                                            <tr className="border-t-2 font-semibold">
                                                {columns.map((col) => (
                                                    <td
                                                        key={col.key}
                                                        className={`px-4 py-3 ${col.key === 'branch' ? '' : 'text-right tabular-nums'}`}
                                                    >
                                                        {col.key === 'branch'
                                                            ? 'Grand Total'
                                                            : formatCurrency(
                                                                  grand_total[
                                                                      col.key as keyof GrandTotal
                                                                  ],
                                                              )}
                                                    </td>
                                                ))}
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </PayrollLayout>
    );
}
