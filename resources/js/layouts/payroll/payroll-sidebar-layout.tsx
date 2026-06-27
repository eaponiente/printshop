import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { Toaster } from '@/components/ui/sonner';
import type { AppLayoutProps } from '@/types';
import { PayrollSidebar } from './payroll-sidebar';
import { PayrollSidebarHeader } from './payroll-sidebar-header';

export default function PayrollSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <PayrollSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <PayrollSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
                <Toaster
                    closeButton
                    toastOptions={{
                        classNames: {
                            toast: 'bg-blue-500', // Default background for all toasts
                            title: 'text-white',
                            description: 'text-red-200',
                            actionButton: 'bg-zinc-400',
                            cancelButton: 'bg-orange-400',
                            success:
                                'text-green-500 bg-green-50 border-green-200', // Custom success styles
                            error: 'text-red-500 bg-red-50 border-red-200', // Custom success styles
                        },
                    }}
                />
            </AppContent>
        </AppShell>
    );
}
