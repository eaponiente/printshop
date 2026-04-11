import { Head } from '@inertiajs/react';
import {
    Activity,
    CreditCard,
    DollarSign,
    Users,
    ArrowUpRight,
    Plus,
} from 'lucide-react';

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
import { route } from 'ziggy-js';

// Shadcn UI Components (Assuming standard installation paths)

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

export default function Dashboard({ chartData, pieData }: any) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4">
                {/* 1. HEADER SECTION: Greet the user and provide quick actions */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">
                            Welcome back, Admin
                        </h2>
                        <p className="text-muted-foreground">
                            Here is what's happening with your business today.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button size="sm" variant="outline">
                            Download Report
                        </Button>
                        <Button size="sm">
                            <Plus className="mr-2 h-4 w-4" /> New Transaction
                        </Button>
                    </div>
                </div>

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
                            <div className="text-2xl font-bold">$45,231.89</div>
                            <p className="flex items-center gap-1 text-xs text-green-500">
                                +20.1%{' '}
                                <span className="text-muted-foreground">
                                    from last month
                                </span>
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Subscriptions
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">+2,350</div>
                            <p className="flex items-center gap-1 text-xs text-green-500">
                                +180.1%{' '}
                                <span className="text-muted-foreground">
                                    from last month
                                </span>
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Sales
                            </CardTitle>
                            <CreditCard className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">+12,234</div>
                            <p className="text-xs text-muted-foreground">
                                +19% from last month
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">
                                Active Now
                            </CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">+573</div>
                            <p className="text-xs text-muted-foreground">
                                +201 since last hour
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
                                Daily breakdown of total billed vs. actual
                                collections.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="h-[350px]">
                            <TransactionBarChart chartData={chartData} />
                        </CardContent>
                    </Card>

                    {/* Recent Transactions (New useful component for Staff) */}
                    <Card className="lg:col-span-3">
                        <CardHeader className="flex flex-row items-center">
                            <div className="grid gap-1">
                                <CardTitle>Recent Transactions</CardTitle>
                                <CardDescription>
                                    You made 265 sales this month.
                                </CardDescription>
                            </div>
                            <Button asChild size="sm" className="ml-auto gap-1">
                                <a href={route('sales.index')}>
                                    View All{' '}
                                    <ArrowUpRight className="h-4 w-4" />
                                </a>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-8">
                                {[1, 2, 3, 4, 5].map((_, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center gap-4"
                                    >
                                        <Avatar className="h-9 w-9">
                                            <AvatarFallback>OM</AvatarFallback>
                                        </Avatar>
                                        <div className="grid gap-1">
                                            <p className="text-sm leading-none font-medium">
                                                Olivia Martin
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                olivia.martin@email.com
                                            </p>
                                        </div>
                                        <div className="ml-auto font-medium">
                                            +$1,999.00
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Monthly Distribution (Shifted to bottom row) */}
                    <Card className="lg:col-span-4">
                        <CardHeader>
                            <CardTitle>Monthly Distribution</CardTitle>
                            <CardDescription>
                                Total revenue share by month for the last 6
                                months.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="h-[300px]">
                            <MonthlyPieChart pieData={pieData} />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
