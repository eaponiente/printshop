import { Head, router, usePage } from '@inertiajs/react';
import { Banknote, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Reports', href: '/payroll/reports' },
    { title: 'Branch Payables', href: '#' },
];

type PeriodOption = {
    id: number;
    branch_id: number;
    branch: { name: string };
    period_start: string;
    period_end: string;
    status: string;
};

type PeriodResult = {
    period_id: number;
    branch: string;
    period_start: string;
    period_end: string;
    sss_employee: number;
    sss_employer: number;
    sss_total: number;
    philhealth_employee: number;
    philhealth_employer: number;
    philhealth_total: number;
    pagibig_employee: number;
    pagibig_employer: number;
    pagibig_total: number;
};

type GrandTotal = {
    sss_employee: number;
    sss_employer: number;
    sss_total: number;
    philhealth_employee: number;
    philhealth_employer: number;
    philhealth_total: number;
    pagibig_employee: number;
    pagibig_employer: number;
    pagibig_total: number;
} | null;

type Props = {
    periods: PeriodOption[];
    results: PeriodResult[];
    grand_total: GrandTotal;
    filters?: { period_ids?: number[] };
};

function statusLabel(status: string) {
    return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
}

function formatPeriod(start: string, end: string) {
    const fmt = (d: string) => {
        const [y, m, day] = d.split('-');
        const months = [
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
        ];
        return `${months[Number(m) - 1]} ${Number(day)}, ${y}`;
    };
    return `${fmt(start)} – ${fmt(end)}`;
}

export default function BranchPayables({
    periods,
    results,
    grand_total,
    filters,
}: Props) {
    const { auth } = usePage().props as any;
    const role = auth?.user?.role;
    const isAuthorized = role === 'superadmin' || role === 'admin';

    const [selected, setSelected] = useState<number[]>(
        filters?.period_ids ?? [],
    );
    const [search, setSearch] = useState('');

    if (!isAuthorized) {
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

    const filteredPeriods = useMemo(() => {
        const q = search.toLowerCase();
        if (!q) return periods;
        return periods.filter(
            (p) =>
                p.branch.name.toLowerCase().includes(q) ||
                p.period_start.includes(q) ||
                p.period_end.includes(q),
        );
    }, [periods, search]);

    const togglePeriod = (id: number) => {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    };

    const selectAll = () => setSelected(filteredPeriods.map((p) => p.id));
    const clearAll = () => setSelected([]);

    const handleView = () => {
        if (selected.length === 0) return;
        router.get(
            '/payroll/reports/branch-payables',
            { period_ids: selected },
            { preserveState: true, replace: true },
        );
    };

    const hasResults = results.length > 0;

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Branch Payables" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">
                        Branch Payables Report
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Select payroll periods to compute total remittable SSS,
                        PhilHealth, and Pag-IBIG contributions.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Select Periods</CardTitle>
                        <CardDescription>
                            {periods.length === 0
                                ? 'No approved or paid periods available.'
                                : `${periods.length} period${periods.length !== 1 ? 's' : ''} available · ${selected.length} selected`}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="relative">
                            <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search by branch or date…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-8"
                            />
                        </div>

                        {filteredPeriods.length === 0 ? (
                            <p className="py-4 text-center text-sm text-muted-foreground">
                                No periods match your search.
                            </p>
                        ) : (
                            <div className="max-h-72 overflow-y-auto rounded-md border">
                                {filteredPeriods.map((p) => {
                                    const checked = selected.includes(p.id);
                                    return (
                                        <label
                                            key={p.id}
                                            className="flex cursor-pointer items-center gap-3 border-b px-4 py-3 last:border-b-0 hover:bg-muted/50"
                                        >
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={() =>
                                                    togglePeriod(p.id)
                                                }
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium">
                                                    {formatPeriod(
                                                        p.period_start,
                                                        p.period_end,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {p.branch.name}
                                                </p>
                                            </div>
                                            <Badge
                                                variant={
                                                    p.status === 'paid'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                                className="shrink-0 text-xs"
                                            >
                                                {statusLabel(p.status)}
                                            </Badge>
                                        </label>
                                    );
                                })}
                            </div>
                        )}

                        <div className="flex items-center justify-between gap-2">
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={selectAll}
                                    disabled={filteredPeriods.length === 0}
                                >
                                    Select All
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={clearAll}
                                    disabled={selected.length === 0}
                                >
                                    Clear
                                </Button>
                            </div>
                            <Button
                                onClick={handleView}
                                disabled={selected.length === 0}
                            >
                                <Banknote className="mr-2 h-4 w-4" />
                                View Report
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {hasResults && grand_total && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Results</CardTitle>
                            <CardDescription>
                                {results.length} period
                                {results.length !== 1 ? 's' : ''} · employee +
                                employer contributions
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-xs">
                                    <thead>
                                        <tr className="border-b bg-muted/50">
                                            <th
                                                rowSpan={2}
                                                className="px-4 py-2 text-left font-semibold align-bottom"
                                            >
                                                Period
                                            </th>
                                            <th
                                                rowSpan={2}
                                                className="px-4 py-2 text-left font-semibold align-bottom"
                                            >
                                                Branch
                                            </th>
                                            <th
                                                colSpan={3}
                                                className="border-l px-4 py-2 text-center font-semibold"
                                            >
                                                SSS
                                            </th>
                                            <th
                                                colSpan={3}
                                                className="border-l px-4 py-2 text-center font-semibold"
                                            >
                                                PhilHealth
                                            </th>
                                            <th
                                                colSpan={3}
                                                className="border-l px-4 py-2 text-center font-semibold"
                                            >
                                                Pag-IBIG
                                            </th>
                                        </tr>
                                        <tr className="border-b">
                                            <th className="border-l px-3 py-1.5 text-right font-medium text-muted-foreground">
                                                Employee
                                            </th>
                                            <th className="px-3 py-1.5 text-right font-medium text-muted-foreground">
                                                Employer
                                            </th>
                                            <th className="px-3 py-1.5 text-right font-semibold">
                                                Total
                                            </th>
                                            <th className="border-l px-3 py-1.5 text-right font-medium text-muted-foreground">
                                                Employee
                                            </th>
                                            <th className="px-3 py-1.5 text-right font-medium text-muted-foreground">
                                                Employer
                                            </th>
                                            <th className="px-3 py-1.5 text-right font-semibold">
                                                Total
                                            </th>
                                            <th className="border-l px-3 py-1.5 text-right font-medium text-muted-foreground">
                                                Employee
                                            </th>
                                            <th className="px-3 py-1.5 text-right font-medium text-muted-foreground">
                                                Employer
                                            </th>
                                            <th className="px-3 py-1.5 text-right font-semibold">
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {results.map((row) => (
                                            <tr
                                                key={row.period_id}
                                                className="border-b hover:bg-muted/30"
                                            >
                                                <td className="px-4 py-2 font-medium whitespace-nowrap">
                                                    {formatPeriod(
                                                        row.period_start,
                                                        row.period_end,
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-muted-foreground whitespace-nowrap">
                                                    {row.branch}
                                                </td>
                                                <td className="border-l px-3 py-2 text-right tabular-nums text-muted-foreground">
                                                    {formatCurrency(
                                                        row.sss_employee,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">
                                                    {formatCurrency(
                                                        row.sss_employer,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums font-medium">
                                                    {formatCurrency(
                                                        row.sss_total,
                                                    )}
                                                </td>
                                                <td className="border-l px-3 py-2 text-right tabular-nums text-muted-foreground">
                                                    {formatCurrency(
                                                        row.philhealth_employee,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">
                                                    {formatCurrency(
                                                        row.philhealth_employer,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums font-medium">
                                                    {formatCurrency(
                                                        row.philhealth_total,
                                                    )}
                                                </td>
                                                <td className="border-l px-3 py-2 text-right tabular-nums text-muted-foreground">
                                                    {formatCurrency(
                                                        row.pagibig_employee,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">
                                                    {formatCurrency(
                                                        row.pagibig_employer,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums font-medium">
                                                    {formatCurrency(
                                                        row.pagibig_total,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}

                                        <tr className="border-t-2 bg-muted/30 font-semibold">
                                            <td
                                                colSpan={2}
                                                className="px-4 py-3"
                                            >
                                                Grand Total
                                            </td>
                                            <td className="border-l px-3 py-3 text-right tabular-nums text-muted-foreground">
                                                {formatCurrency(
                                                    grand_total.sss_employee,
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right tabular-nums text-muted-foreground">
                                                {formatCurrency(
                                                    grand_total.sss_employer,
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right tabular-nums">
                                                {formatCurrency(
                                                    grand_total.sss_total,
                                                )}
                                            </td>
                                            <td className="border-l px-3 py-3 text-right tabular-nums text-muted-foreground">
                                                {formatCurrency(
                                                    grand_total.philhealth_employee,
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right tabular-nums text-muted-foreground">
                                                {formatCurrency(
                                                    grand_total.philhealth_employer,
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right tabular-nums">
                                                {formatCurrency(
                                                    grand_total.philhealth_total,
                                                )}
                                            </td>
                                            <td className="border-l px-3 py-3 text-right tabular-nums text-muted-foreground">
                                                {formatCurrency(
                                                    grand_total.pagibig_employee,
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right tabular-nums text-muted-foreground">
                                                {formatCurrency(
                                                    grand_total.pagibig_employer,
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right tabular-nums">
                                                {formatCurrency(
                                                    grand_total.pagibig_total,
                                                )}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </PayrollLayout>
    );
}
