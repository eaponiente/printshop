import { Head, Link, usePage } from '@inertiajs/react';
import { Banknote, Eye } from 'lucide-react';
import { useState } from 'react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'Reports', href: '#' },
];

type Props = {
    branches: { id: number; name: string }[];
    periods: {
        id: number;
        branch_id: number;
        branch: { name: string };
        period_start: string;
        period_end: string;
        status: string;
    }[];
};

export default function ReportsIndex({ branches, periods }: Props) {
    const { auth } = usePage().props as any;
    const isSuperAdmin = auth?.user?.role === 'superadmin';

    const [branchId, setBranchId] = useState<string>('');
    const [periodId, setPeriodId] = useState<string>('');

    const filteredPeriods = periods.filter(
        (p) => !branchId || p.branch_id === Number(branchId),
    );

    const canPrint = branchId && periodId;

    const handlePrint = () => {
        if (!canPrint) {
return;
}

        const url = route('payroll.reports.print', {
            branch_id: branchId,
            period_id: periodId,
        });
        window.open(url, '_blank');
    };

    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll Reports" />
            <div className="mx-auto flex h-full max-w-lg flex-col gap-6 p-4">
                <div>
                    <h1 className="text-lg font-semibold">Payroll Reports</h1>
                    <p className="text-sm text-muted-foreground">
                        View and print payroll reports.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Payslips</CardTitle>
                        <CardDescription>
                            Print payslips for all employees in a branch and pay
                            period.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Branch
                            </label>
                            <Select
                                value={branchId}
                                onValueChange={(v) => {
                                    setBranchId(v);
                                    setPeriodId('');
                                }}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select branch" />
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

                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Pay Period
                            </label>
                            <Select
                                value={periodId}
                                onValueChange={setPeriodId}
                                disabled={!branchId}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue
                                        placeholder={
                                            branchId
                                                ? 'Select period'
                                                : 'Select branch first'
                                        }
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    {filteredPeriods.length === 0 && (
                                        <div className="px-2 py-4 text-center text-sm text-muted-foreground">
                                            {branchId
                                                ? 'No approved periods for this branch'
                                                : ''}
                                        </div>
                                    )}
                                    {filteredPeriods.map((p) => (
                                        <SelectItem
                                            key={p.id}
                                            value={String(p.id)}
                                        >
                                            {p.period_start} to {p.period_end}
                                            {!branchId &&
                                                ` \u2014 ${p.branch.name}`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <Button
                            className="w-full"
                            size="lg"
                            disabled={!canPrint}
                            onClick={handlePrint}
                        >
                            <Eye className="mr-2 h-4 w-4" />
                            View Payslips
                        </Button>
                    </CardContent>
                </Card>

                {isSuperAdmin && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Branch Payables</CardTitle>
                            <CardDescription>
                                View total employer benefits per branch (SSS,
                                PhilHealth, Pag-IBIG, deminimis) over a custom
                                date range.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button className="w-full" size="lg" asChild>
                                <Link
                                    href={route(
                                        'payroll.reports.branch-payables',
                                    )}
                                >
                                    <Banknote className="mr-2 h-4 w-4" />
                                    View Branch Payables
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </PayrollLayout>
    );
}
