import PayrollSidebarLayout from '@/layouts/payroll/payroll-sidebar-layout';
import type { AppLayoutProps } from '@/types';

export default ({ children, breadcrumbs, ...props }: AppLayoutProps) => (
    <PayrollSidebarLayout breadcrumbs={breadcrumbs} {...props}>
        {children}
    </PayrollSidebarLayout>
);
