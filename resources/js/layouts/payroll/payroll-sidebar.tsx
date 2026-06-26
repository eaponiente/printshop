import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    BriefcaseBusiness,
    CalendarDays,
    ClipboardList,
    Clock,
    FileEdit,
    FileText,
    HandCoins,
    LayoutGrid,
    MapPin,
    Printer,
    ScrollText,
    Settings,
    Shield,
    Table,
    UserRound,
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
import type { NavItem } from '@/types';

const attendanceNav: NavItem[] = [
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
];

const managementNav: NavItem[] = [
    {
        title: 'Employees',
        href: '/payroll/employees',
        icon: UserRound,
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
];

const superadminNav: NavItem[] = [
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
];

export function PayrollSidebar() {
    const { auth, pending_requests } = usePage().props as any;
    const isSuperAdmin = auth?.user?.role === 'superadmin';
    const isAdmin = auth?.user?.role === 'admin';
    const canManageAttendance = isSuperAdmin || isAdmin;

    const requestsNav: NavItem[] = [
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
        ...(canManageAttendance
            ? [
                  {
                      title: 'Cash Advances',
                      href: '/payroll/cash-advances',
                      icon: HandCoins,
                      badge: pending_requests?.cash_advance,
                  } as NavItem,
              ]
            : []),
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
                {!isSuperAdmin && (
                    <SidebarGroup>
                        <SidebarGroupLabel>Attendance</SidebarGroupLabel>
                        <NavMain items={attendanceNav} />
                    </SidebarGroup>
                )}

                <SidebarGroup>
                    <SidebarGroupLabel>Requests</SidebarGroupLabel>
                    <NavMain items={requestsNav} />
                </SidebarGroup>

                <SidebarGroup>
                    <SidebarGroupLabel>Sewed</SidebarGroupLabel>
                    <NavMain
                        items={[
                            {
                                title: 'Sewed Items',
                                href: '/payroll/sewed-items',
                                icon: BriefcaseBusiness,
                            },
                        ]}
                    />
                </SidebarGroup>

                {canManageAttendance && (
                    <SidebarGroup>
                        <SidebarGroupLabel>Management</SidebarGroupLabel>
                        <NavMain
                            items={[
                                {
                                    title: 'Attendance Sheets',
                                    href: '/payroll/attendance-sheets',
                                    icon: Table,
                                },
                                ...managementNav,
                            ]}
                        />
                    </SidebarGroup>
                )}

                {isSuperAdmin && (
                    <SidebarGroup>
                        <SidebarGroupLabel>Administration</SidebarGroupLabel>
                        <NavMain items={superadminNav} />
                    </SidebarGroup>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={[]} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
