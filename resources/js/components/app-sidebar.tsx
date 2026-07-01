import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    BriefcaseBusiness,
    CalendarDays,
    ClipboardList,
    Clock,
    FileEdit,
    FileText,
    Folder,
    HandCoins,
    LayoutGrid,
    MapPin,
    Newspaper,
    NotebookPen,
    Printer,
    ScrollText,
    Settings,
    Shield,
    Shirt,
    ShoppingCart,
    Table,
    Tag,
    UserRound,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import endorsements from '@/routes/endorsements';
import expenses from '@/routes/expenses';
import purchaseOrders from '@/routes/purchase-orders';
import sales from '@/routes/sales';
import sublimations from '@/routes/sublimations';
import type { NavGroup, NavItem } from '@/types';

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth, pending_requests } = usePage().props;

    const role = auth.user.role;
    const isSuperAdmin = role === 'superadmin';
    const isManager = role === 'admin' || role === 'superadmin';

    const navGroups: NavGroup[] = [
        {
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(),
                    icon: LayoutGrid,
                },
            ],
        },
        {
            label: 'Operations',
            items: [
                {
                    title: 'Projects',
                    href: sales.index(),
                    icon: Folder,
                },
                {
                    title: 'Sublimation',
                    href: sublimations.index(),
                    icon: Shirt,
                },
                {
                    title: 'Sewed Items',
                    href: '/payroll/sewed-items',
                    icon: BriefcaseBusiness,
                },
            ],
        },
        {
            label: 'My Payroll',
            items: [
                {
                    title: 'My Attendance',
                    href: '/payroll/attendance',
                    icon: Clock,
                },
                {
                    title: 'My Payslip',
                    href: '/payroll/my-payslip',
                    icon: FileText,
                },
            ],
        },
        {
            label: 'Requests',
            items: [
                {
                    title: 'Leave Requests',
                    href: '/payroll/leave-requests',
                    icon: ClipboardList,
                    badge: pending_requests?.leave,
                },
                {
                    title: 'Corrections',
                    href: '/payroll/correction-requests',
                    icon: FileEdit,
                    badge: pending_requests?.correction,
                },
                {
                    title: 'Cash Advances',
                    href: '/payroll/cash-advances',
                    icon: HandCoins,
                    badge: pending_requests?.cash_advance,
                },
            ],
        },
        {
            label: 'Business',
            visible: isManager,
            items: [
                {
                    title: 'Users',
                    href: '#',
                    icon: Users,
                    items: [
                        { title: 'Users', url: '/users' },
                        { title: 'Customers', url: '/customers' },
                    ],
                },
                {
                    title: 'Expenses',
                    href: expenses.index(),
                    icon: NotebookPen,
                },
                {
                    title: 'Purchase Orders',
                    href: purchaseOrders.index(),
                    icon: ShoppingCart,
                },
                {
                    title: 'Endorsements',
                    href: endorsements.index(),
                    icon: Newspaper,
                },
                {
                    title: 'Incentives',
                    href: '/incentives',
                    icon: Banknote,
                },
                {
                    title: 'Tags',
                    href: '/tags',
                    icon: Tag,
                },
            ],
        },
        {
            label: 'Payroll & HR',
            visible: isManager,
            items: [
                {
                    title: 'Employees',
                    href: '/payroll/employees',
                    icon: UserRound,
                },
                {
                    title: 'Attendance Sheets',
                    href: '/payroll/attendance-sheets',
                    icon: Table,
                },
                {
                    title: 'Payroll Periods',
                    href: '/payroll/periods',
                    icon: Banknote,
                },
                {
                    title: 'Reports',
                    href: '/payroll/reports',
                    icon: Printer,
                },
            ],
        },
        {
            label: 'Administration',
            visible: isSuperAdmin,
            items: [
                {
                    title: 'Branches',
                    href: '/branches',
                    icon: Folder,
                },
                {
                    title: 'Holidays',
                    href: '/payroll/holidays',
                    icon: CalendarDays,
                },
                {
                    title: 'Attendance Geo',
                    href: '/payroll/attendance-geo',
                    icon: MapPin,
                },
                {
                    title: 'SSS Brackets',
                    href: '/payroll/sss-brackets',
                    icon: Shield,
                },
                {
                    title: 'Company Config',
                    href: '/payroll/company-config',
                    icon: Settings,
                },
                {
                    title: 'Payroll Settings',
                    href: '/payroll/settings',
                    icon: Settings,
                },
                {
                    title: 'Audit Logs',
                    href: '/payroll/audit-logs',
                    icon: ScrollText,
                },
            ],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {navGroups
                    .filter((group) => group.visible !== false)
                    .map((group, i) => (
                        <SidebarGroup key={group.label ?? i}>
                            {group.label && (
                                <SidebarGroupLabel>
                                    {group.label}
                                </SidebarGroupLabel>
                            )}
                            <NavMain items={group.items} />
                        </SidebarGroup>
                    ))}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
