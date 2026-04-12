import { Head, usePage } from '@inertiajs/react';
import {
    Activity,
    CreditCard,
    DollarSign,
    Users,
    ArrowUpRight,
    Plus,
} from 'lucide-react';

import { route } from 'ziggy-js';
import MonthlyPieChart from '@/components/charts/monthly-pie-chart';
import TransactionBarChart from '@/components/charts/transaction-bar-chart';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { formatCurrency } from '@/utils/formatters';

// Shadcn UI Components (Assuming standard installation paths)

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

export default function Dashboard({
    chartData,
    pieData,
    stats,
    recentTransactions,
}: any) {
    const { auth } = usePage<any>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">
                {/* 1. HEADER SECTION: Greet the user and provide quick actions */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">
                            Welcome back, {auth?.user?.first_name || 'Admin'}
                        </h2>
                        <p className="text-muted-foreground">
                            Here is what's happening with your business today.
                        </p>
                    </div>
                </div>

                {auth.user.role === 'superadmin' && (
                    <>
                        {/* 2. STAT CARDS: The "At-a-glance" metrics for CEO/Staff */}
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-sm font-medium">
                                        Total Revenue
                                    </CardTitle>
                                    <DollarSign className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {formatCurrency(stats.revenue.value)}
                                    </div>
                                    <p
                                        className={`flex items-center gap-1 text-xs ${stats.revenue.growth >= 0 ? 'text-green-500' : 'text-red-500'}`}
                                    >
                                        {stats.revenue.growth > 0 ? '+' : ''}
                                        {stats.revenue.growth}%{' '}
                                        <span className="text-muted-foreground">
                                            from last month
                                        </span>
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-sm font-medium">
                                        New Customers
                                    </CardTitle>
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {stats.customers.value > 0 ? '+' : ''}
                                        {stats.customers.value}
                                    </div>
                                    <p
                                        className={`flex items-center gap-1 text-xs ${stats.customers.growth >= 0 ? 'text-green-500' : 'text-red-500'}`}
                                    >
                                        {stats.customers.growth > 0 ? '+' : ''}
                                        {stats.customers.growth}%{' '}
                                        <span className="text-muted-foreground">
                                            from last month
                                        </span>
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-sm font-medium">
                                        Total Sales
                                    </CardTitle>
                                    <CreditCard className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {stats.sales.value > 0 ? '+' : ''}
                                        {stats.sales.value}
                                    </div>
                                    <p
                                        className={`flex items-center gap-1 text-xs ${stats.sales.growth >= 0 ? 'text-green-500' : 'text-red-500'}`}
                                    >
                                        {stats.sales.growth > 0 ? '+' : ''}
                                        {stats.sales.growth}%{' '}
                                        <span className="text-muted-foreground">
                                            from last month
                                        </span>
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="text-sm font-medium">
                                        Pending Transactions
                                    </CardTitle>
                                    <Activity className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {stats.pending_jobs.value}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        +{stats.pending_jobs.added_today} added
                                        today
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* 3. MAIN CHARTS & ACTIVITY SECTION */}
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
                            {/* Revenue Analytics (Expanded) */}
                            <Card className="lg:col-span-4">
                                <CardHeader>
                                    <CardTitle>Revenue Analytics</CardTitle>
                                    <CardDescription>
                                        Daily breakdown of total billed vs.
                                        actual collections.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="h-[350px]">
                                    <TransactionBarChart
                                        chartData={chartData}
                                    />
                                </CardContent>
                            </Card>

                            {/* Recent Transactions (New useful component for Staff) */}
                            <Card className="lg:col-span-3">
                                <CardHeader className="flex flex-row items-center">
                                    <div className="grid gap-1">
                                        <CardTitle>
                                            Recent Transactions
                                        </CardTitle>
                                        <CardDescription>
                                            You made {stats.sales.value} sales
                                            this month.
                                        </CardDescription>
                                    </div>
                                    <Button
                                        asChild
                                        size="sm"
                                        className="ml-auto gap-1"
                                    >
                                        <a href={route('sales.index')}>
                                            View All{' '}
                                            <ArrowUpRight className="h-4 w-4" />
                                        </a>
                                    </Button>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-8">
                                        {recentTransactions.map(
                                            (transaction: any) => (
                                                <div
                                                    key={transaction.id}
                                                    className="flex items-center gap-4"
                                                >
                                                    <Avatar className="h-9 w-9">
                                                        <AvatarFallback>
                                                            {transaction.customer_name
                                                                .substring(0, 2)
                                                                .toUpperCase()}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="grid gap-1">
                                                        <p className="text-sm leading-none font-medium">
                                                            {
                                                                transaction.customer_name
                                                            }
                                                        </p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {
                                                                transaction.customer_company
                                                            }
                                                        </p>
                                                    </div>
                                                    <div className="ml-auto font-medium">
                                                        {formatCurrency(
                                                            transaction.amount_total,
                                                        )}
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Monthly Distribution (Shifted to bottom row) */}
                            <Card className="lg:col-span-4">
                                <CardHeader>
                                    <CardTitle>Monthly Distribution</CardTitle>
                                    <CardDescription>
                                        Total revenue share by month for the
                                        last 6 months.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="h-[300px]">
                                    <MonthlyPieChart pieData={pieData} />
                                </CardContent>
                            </Card>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
