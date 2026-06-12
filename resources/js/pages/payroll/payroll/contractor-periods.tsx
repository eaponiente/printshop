import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency } from '@/utils/formatters';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contractor Periods', href: '/payroll/contractor-periods' },
];

type ContractorItem = {
    id: number;
    contractor: { id: number; name: string };
    project: { id: number; name: string; contract_amount: number };
    contract_amount: number;
    ca_deduction: number;
    net_pay: number;
};

type Period = {
    id: number;
    branch: { id: number; name: string };
    period_start: string;
    period_end: string;
    status: string;
    contractor_items: ContractorItem[];
};

type Props = {
    periods: {
        data: Period[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    branches: { id: number; name: string }[];
};

export default function ContractorPeriods({ periods, branches }: Props) {
    const [showForm, setShowForm] = useState(false);

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Contractor Payroll Periods" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">
                        Contractor Payroll Periods
                    </h1>
                    <Button size="sm" onClick={() => setShowForm(!showForm)}>
                        {showForm ? 'Cancel' : 'Generate Period'}
                    </Button>
                </div>

                {showForm && (
                    <form
                        className="rounded-md border bg-sidebar p-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const form = e.currentTarget as HTMLFormElement;
                            const data = new FormData(form);
                            router.post('/payroll/contractor-periods', data, {
                                onSuccess: () => {
                                    form.reset();
                                    setShowForm(false);
                                },
                            });
                        }}
                    >
                        <div className="flex flex-wrap gap-3">
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Branch
                                </label>
                                <select
                                    name="branch_id"
                                    required
                                    className="rounded border px-2 py-1 text-sm"
                                >
                                    <option value="">Select...</option>
                                    {branches.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Period Start
                                </label>
                                <input
                                    name="period_start"
                                    type="date"
                                    required
                                    className="rounded border px-2 py-1 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Period End
                                </label>
                                <input
                                    name="period_end"
                                    type="date"
                                    required
                                    className="rounded border px-2 py-1 text-sm"
                                />
                            </div>
                        </div>
                        <div className="mt-3">
                            <Button type="submit" size="sm">
                                Generate
                            </Button>
                        </div>
                    </form>
                )}

                <div className="overflow-x-auto rounded-md border bg-sidebar">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="px-3 py-2 text-left">Branch</th>
                                <th className="px-3 py-2 text-left">Period</th>
                                <th className="px-3 py-2 text-center">Items</th>
                                <th className="px-3 py-2 text-right">Total</th>
                                <th className="px-3 py-2 text-center">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {periods.data.map((p) => {
                                const total = p.contractor_items.reduce(
                                    (sum, i) => sum + Number(i.net_pay),
                                    0,
                                );
                                return (
                                    <tr key={p.id} className="border-b">
                                        <td className="px-3 py-2">
                                            {p.branch.name}
                                        </td>
                                        <td className="px-3 py-2">
                                            <button
                                                type="button"
                                                className="hover:underline"
                                                onClick={() =>
                                                    router.get(
                                                        `/payroll/contractor-periods/${p.id}`,
                                                    )
                                                }
                                            >
                                                {p.period_start} to{' '}
                                                {p.period_end}
                                            </button>
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            {p.contractor_items.length}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono">
                                            {formatCurrency(total)}
                                        </td>
                                        <td className="px-3 py-2 text-center">
                                            <span
                                                className={
                                                    p.status === 'approved'
                                                        ? 'text-green-600'
                                                        : p.status === 'voided'
                                                          ? 'text-red-500'
                                                          : 'text-amber-600'
                                                }
                                            >
                                                {p.status}
                                            </span>
                                        </td>
                                    </tr>
                                );
                            })}
                            {periods.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No contractor periods yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </PayrollLayout>
    );
}
