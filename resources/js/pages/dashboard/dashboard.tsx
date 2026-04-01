import { Head } from '@inertiajs/react';
import MonthlyPieChart from '@/components/charts/monthly-pie-chart';
import TransactionBarChart from '@/components/charts/transaction-bar-chart';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

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
            <div className="grid auto-rows-min gap-4 md:grid-cols-2">
                {/* Bar Chart Card */}
                <div className="rounded-xl border border-sidebar-border/70 bg-card p-6 shadow-sm">
                    {/* Title and Subtitle above the Bar Chart */}
                    <div className="flex flex-col gap-1.5 pb-6">
                        <h3 className="font-semibold leading-none tracking-tight text-lg">
                            Revenue Analytics
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Daily breakdown of total billed vs. actual collections.
                        </p>
                    </div>
                    <div className="h-[300px] w-full">
                        <TransactionBarChart chartData={chartData} />
                    </div>
                </div>

                {/* Pie Chart Card */}
                <div className="rounded-xl border border-sidebar-border/70 bg-card p-6 shadow-sm">
                    {/* Title and Subtitle above the Pie Chart */}
                    <div className="flex flex-col gap-1.5 pb-6">
                        <h3 className="font-semibold leading-none tracking-tight text-lg">
                            Monthly Distribution
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Total revenue share by month for the last 6 months.
                        </p>
                    </div>
                    <div className="h-[300px] w-full">
                        <MonthlyPieChart pieData={pieData} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
