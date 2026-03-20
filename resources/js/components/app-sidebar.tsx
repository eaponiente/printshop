import { Link } from '@inertiajs/react';
import { BadgeDollarSign, Cog, LayoutGrid, Shirt, Store, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import sales from '@/routes/sales';
import sublimations from '@/routes/sublimations';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Users',
        href: '#', // Parent doesn't need a real link if it's just a toggle
        icon: Users,
        isActive: true, // Optional: keeps it open by default
        items: [
            {
                title: 'Users List',
                url: '/users', // Your route here
            },
            {
                title: 'Roles & Permissions',
                url: '/roles',
            },
        ],
    },
    {
        title: 'Sales',
        href: sales.index(),
        icon: BadgeDollarSign,
    },
    {
        title: 'Sublimation',
        href: sublimations.index(),
        icon: Shirt,
    },
    {
        title: 'Settings',
        href: '#', // Parent doesn't need a real link if it's just a toggle
        icon: Cog,
        isActive: true, // Optional: keeps it open by default
        items: [
            {
                title: 'Tags',
                url: '/tags', // Your route here
            },
            {
                title: 'Branches',
                url: '/branches', // Your route here
            }
        ],
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {

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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
