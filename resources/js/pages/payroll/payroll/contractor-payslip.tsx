import { Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contractor Payslip', href: '#' },
];

type Props = {
    period: {
        id: number;
        branch: { name: string };
        period_start: string;
        period_end: string;
        status: string;
    };
    contractor: {
        id: number;
        name: string;
    };
    items: {
        id: number;
        project: { id: number; name: string };
        contract_amount: number;
        ca_deduction: number;
        net_pay: number;
    }[];
};

export default function ContractorPayslip({
    period,
    contractor,
    items,
}: Props) {
    const totalGross = items.reduce((s, i) => s + Number(i.contract_amount), 0);
    const totalCA = items.reduce((s, i) => s + Number(i.ca_deduction), 0);
    const totalNet = items.reduce((s, i) => s + Number(i.net_pay), 0);

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title={`Payslip — ${contractor.name}`} />
            <div className="mx-auto flex h-full max-w-2xl flex-col gap-4 p-4">
                <Button
                    variant="ghost"
                    size="sm"
                    className="w-fit"
                    onClick={() =>
                        router.get(`/payroll/contractor-periods/${period.id}`)
                    }
                >
                    <ArrowLeft className="mr-1 h-4 w-4" /> Back to Period
                </Button>

                <div className="rounded-md border bg-sidebar p-6 text-sm">
                    <div className="text-center font-bold">
                        PRINTING SHOP MANAGEMENT
                    </div>
                    <div className="text-center text-xs text-muted-foreground">
                        Branch: {period.branch.name}
                    </div>
                    <div className="mt-2 text-center text-sm font-semibold">
                        CONTRACTOR PAYSLIP — {period.period_start} to{' '}
                        {period.period_end}
                    </div>
                    <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                        <div>
                            <span className="text-muted-foreground">
                                Contractor:{' '}
                            </span>
                            <span className="font-medium">
                                {contractor.name}
                            </span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Status:{' '}
                            </span>
                            <span className="font-medium capitalize">
                                {period.status}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="rounded-md border bg-sidebar p-4">
                        <h3 className="mb-3 text-xs font-semibold text-muted-foreground uppercase">
                            Earnings
                        </h3>
                        <div className="space-y-2 text-sm">
                            {items.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex justify-between"
                                >
                                    <span className="text-muted-foreground">
                                        {item.project.name}
                                    </span>
                                    <span className="font-mono">
                                        {formatCurrency(item.contract_amount)}
                                    </span>
                                </div>
                            ))}
                            <div className="border-t pt-2 font-semibold">
                                <div className="flex justify-between">
                                    <span>TOTAL GROSS</span>
                                    <span className="font-mono">
                                        {formatCurrency(totalGross)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-md border bg-sidebar p-4">
                        <h3 className="mb-3 text-xs font-semibold text-muted-foreground uppercase">
                            Deductions
                        </h3>
                        <div className="space-y-2 text-sm">
                            {totalCA > 0 && (
                                <div className="flex justify-between">
                                    <span>Cash Advance</span>
                                    <span className="font-mono text-red-600">
                                        -{formatCurrency(totalCA)}
                                    </span>
                                </div>
                            )}
                            {totalCA === 0 && (
                                <div className="text-sm text-muted-foreground">
                                    No deductions
                                </div>
                            )}
                            <div className="border-t pt-2 font-semibold">
                                <div className="flex justify-between">
                                    <span>TOTAL DEDUCTIONS</span>
                                    <span className="font-mono text-red-600">
                                        -{formatCurrency(totalCA)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="rounded-md border border-green-200 bg-green-50 p-4 text-center">
                    <div className="text-xs text-green-700">NET PAY</div>
                    <div className="text-2xl font-bold text-green-800">
                        {formatCurrency(totalNet)}
                    </div>
                </div>
            </div>
        </PayrollLayout>
    );
}
