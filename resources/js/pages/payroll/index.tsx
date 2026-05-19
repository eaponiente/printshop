import { Head } from '@inertiajs/react';
import AppHeaderLayout from '@/layouts/app/app-header-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
];

export default function PayrollIndex() {
    return (
        <AppHeaderLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll" />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-8">
                <h1 className="text-2xl font-semibold">Payroll Management</h1>
                <p className="text-muted-foreground">
                    Employee payroll features coming soon.
                </p>
            </div>
        </AppHeaderLayout>
    );
}
