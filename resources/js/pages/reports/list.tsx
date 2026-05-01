import { Head, router, usePage } from '@inertiajs/react';
import { Banknote, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import ReportFilters from '@/pages/reports/components/report-filters';
import { Card, CardContent } from '@/components/ui/card';
import type { BreadcrumbItem } from '@/types';
import type { Branch } from '@/types/branches';
import type { User } from '@/types/user';
import { formatCurrency } from '@/utils/formatters';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
];

interface ReportIndexProps {
    filters: {
        date?: string;
        mode?: string;
        branch_id?: string;
    };
    branches: Branch[];
    total_sales: number;
    gross_revenue: number;
    total_expenses: number;
    net_income: number;
    cash_amount: number;
    gcash_amount: number;
    card_amount: number;
    check_amount: number;
    bank_transfer_amount: number;
    debit_amount: number;
}

export default function ReportIndex({
    filters: initialFilters,
    branches,
    total_sales = 0,
    gross_revenue = 0,
    total_expenses = 0,
    net_income = 0,
    cash_amount = 0,
    gcash_amount = 0,
    card_amount = 0,
    check_amount = 0,
    bank_transfer_amount = 0,
    debit_amount = 0,
}: ReportIndexProps) {
    const { auth } = usePage<{ auth: { user: User } }>().props;

    const [mode, setMode] = useState(initialFilters.mode || 'daily');

    const selectedBranch = useMemo(
        () => branches.find((b) => b.id === Number(initialFilters.branch_id)) || null,
        [branches, initialFilters.branch_id],
    );

    const handleFilterChange = useCallback((
        value: string,
        type: 'mode' | 'date' | 'branch_id',
    ) => {
        const params = { ...initialFilters };

        if (type === 'mode') {
            setMode(value);
            params.mode = value;
            params.date = '';
        } else if (type === 'branch_id') {
            params.branch_id = value;
        } else {
            params.date = value;
        }

        router.get('/reports', params, { preserveState: true, replace: true });
    }, [initialFilters]);

    const clearFilters = useCallback(() => {
        setMode('daily');
        router.get('/reports', {}, { replace: true });
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Financial Reports</h1>
                    <p className="text-sm text-muted-foreground">
                        Payment breakdown and revenue summary.
                    </p>
                </div>

                <div className="rounded-md border border-sidebar-border bg-sidebar">
                    <ReportFilters
                        mode={mode}
                        filters={initialFilters}
                        handleFilterChange={handleFilterChange}
                        clearFilters={clearFilters}
                        branches={branches}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {/* Gross Revenue */}
                    <Card className="border-sidebar-border bg-sidebar">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="mb-1 text-[10px] leading-none font-bold tracking-wider text-muted-foreground uppercase">
                                        Gross Revenue
                                    </p>
                                    <h2 className="text-xl leading-none font-bold">
                                        {formatCurrency(gross_revenue)}
                                    </h2>
                                </div>
                                <TrendingUp className="h-5 w-5 text-emerald-500/40" />
                            </div>
                            <p className="mt-2 text-[10px] text-muted-foreground/70">
                                Total collections before refunds
                            </p>
                        </CardContent>
                    </Card>

                    {/* Net Revenue */}
                    <Card className="border-sidebar-border bg-sidebar">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="mb-1 text-[10px] leading-none font-bold tracking-wider text-muted-foreground uppercase">
                                        Net Revenue
                                    </p>
                                    <h2 className="text-xl leading-none font-bold">
                                        {formatCurrency(total_sales)}
                                    </h2>
                                </div>
                                <Banknote className="h-5 w-5 text-blue-500/40" />
                            </div>
                            <p className="mt-2 text-[10px] text-muted-foreground/70">
                                Gross minus refunds
                            </p>
                        </CardContent>
                    </Card>

                    {/* Expenses */}
                    <Card className="border-sidebar-border bg-sidebar">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="mb-1 text-[10px] leading-none font-bold tracking-wider text-muted-foreground uppercase">
                                        Expenses
                                    </p>
                                    <h2 className="text-xl leading-none font-bold text-destructive/80">
                                        {formatCurrency(total_expenses)}
                                    </h2>
                                </div>
                                <TrendingDown className="h-5 w-5 text-red-500/40" />
                            </div>
                            <p className="mt-2 text-[10px] text-muted-foreground/70">
                                Paid expenses in period
                            </p>
                        </CardContent>
                    </Card>

                    {/* Net Income */}
                    <Card className="border-sidebar-border bg-sidebar">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="mb-1 text-[10px] leading-none font-bold tracking-wider text-muted-foreground uppercase">
                                        Net Income
                                    </p>
                                    <h2 className={`text-xl leading-none font-bold ${net_income >= 0 ? '' : 'text-destructive/80'}`}>
                                        {formatCurrency(net_income)}
                                    </h2>
                                </div>
                                <Wallet className="h-5 w-5 text-primary/40" />
                            </div>
                            <p className="mt-2 text-[10px] text-muted-foreground/70">
                                Net Revenue minus Expenses
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Payment Type Breakdown */}
                <Card className="border-sidebar-border bg-sidebar">
                    <CardContent className="p-4">
                        <p className="mb-4 text-xs font-bold tracking-wider text-muted-foreground uppercase">
                            Payment Type Breakdown
                        </p>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                            {[
                                { label: 'Cash', amount: cash_amount },
                                { label: 'GCash', amount: gcash_amount },
                                { label: 'Bank Transfer', amount: bank_transfer_amount },
                                { label: 'Card', amount: card_amount },
                                { label: 'Check', amount: check_amount },
                                { label: 'Debit', amount: debit_amount },
                            ].map(({ label, amount }) => (
                                <div
                                    key={label}
                                    className="rounded-lg border border-sidebar-border bg-slate-50/50 p-3"
                                >
                                    <p className="mb-1 text-[10px] font-medium text-muted-foreground uppercase">
                                        {label}
                                    </p>
                                    <p className="text-lg font-bold tabular-nums">
                                        {formatCurrency(amount)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
