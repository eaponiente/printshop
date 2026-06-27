import { Head } from '@inertiajs/react';
import PayrollLayout from '@/layouts/payroll/payroll-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payroll', href: '/payroll' },
    { title: 'My Payslip', href: '#' },
];

export default function NoPayslip() {
    return (
        <PayrollLayout breadcrumbs={breadcrumbs}>
            <Head title="My Payslip" />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-4">
                <p className="text-lg text-muted-foreground">
                    No payslip available yet.
                </p>
                <p className="text-sm text-muted-foreground">
                    Payslips are generated after a payroll period is approved.
                </p>
            </div>
        </PayrollLayout>
    );
}
