import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contractor Periods', href: '/payroll/contractor-periods' },
    { title: 'Period Detail', href: '#' },
];

type ContractorItem = {
    id: number;
    contractor: { id: number; name: string };
    project: { id: number; name: string; contract_amount: number };
    contract_amount: number;
    ca_deduction: number;
    net_pay: number;
};

type Props = {
    period: {
        id: number;
        branch: { id: number; name: string };
        period_start: string;
        period_end: string;
        status: string;
        contractor_items: ContractorItem[];
    };
};

export default function ContractorPeriodShow({ period }: Props) {
    const items = period.contractor_items;
    const totalGross = items.reduce((s, i) => s + Number(i.contract_amount), 0);
    const totalDeductions = items.reduce(
        (s, i) => s + Number(i.ca_deduction),
        0,
    );
    const totalNet = items.reduce((s, i) => s + Number(i.net_pay), 0);
    const isDraft = period.status === 'draft';

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`Period ${period.period_start} — ${period.period_end}`}
            />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.get('/payroll/contractor-periods')
                            }
                        >
                            <ArrowLeft className="mr-1 h-4 w-4" /> Back
                        </Button>
                        <h1 className="text-xl font-semibold">
                            {period.branch.name} — {period.period_start} to{' '}
                            {period.period_end}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Status: {period.status}
                        </p>
                    </div>
                    {isDraft && (
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        `/payroll/contractor-periods/${period.id}/approve`,
                                    )
                                }
                            >
                                Approve
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => {
                                    if (confirm('Delete this period?')) {
                                        router.delete(
                                            `/payroll/contractor-periods/${period.id}`,
                                        );
                                    }
                                }}
                            >
                                Delete
                            </Button>
                        </div>
                    )}
                </div>

                <div className="overflow-x-auto rounded-md border bg-sidebar">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50">
                                <th className="px-3 py-2 text-left">
                                    Contractor
                                </th>
                                <th className="px-3 py-2 text-left">Project</th>
                                <th className="px-3 py-2 text-right">Amount</th>
                                <th className="px-3 py-2 text-right">
                                    CA Deduction
                                </th>
                                <th className="px-3 py-2 text-right">
                                    Net Pay
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((i) => (
                                <tr key={i.id} className="border-b">
                                    <td className="px-3 py-2 font-medium">
                                        {i.contractor.name}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {i.project.name}
                                    </td>
                                    <td className="px-3 py-2 text-right font-mono">
                                        {formatCurrency(i.contract_amount)}
                                    </td>
                                    <td className="px-3 py-2 text-right font-mono text-red-600">
                                        {Number(i.ca_deduction) > 0
                                            ? `-${formatCurrency(i.ca_deduction)}`
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-2 text-right font-mono font-semibold">
                                        {formatCurrency(i.net_pay)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t bg-muted/30 font-semibold">
                                <td colSpan={2} className="px-3 py-2">
                                    Total
                                </td>
                                <td className="px-3 py-2 text-right font-mono">
                                    {formatCurrency(totalGross)}
                                </td>
                                <td className="px-3 py-2 text-right font-mono text-red-600">
                                    -{formatCurrency(totalDeductions)}
                                </td>
                                <td className="px-3 py-2 text-right font-mono">
                                    {formatCurrency(totalNet)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </PayrollLayout>
    );
}
