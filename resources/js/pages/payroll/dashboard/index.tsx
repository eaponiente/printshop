import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

// Shadcn UI Components (Assuming standard installation paths)

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

export default function Dashboard() {
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
            </div>
        </AppLayout>
    );
}
