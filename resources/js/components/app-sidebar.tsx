import { Link, usePage } from '@inertiajs/react';
import {
    BadgeDollarSign,
    Cog,
    LayoutGrid,
    Newspaper,
    NotebookPen,
    Shirt,
    ShoppingCart,
    Users
} from 'lucide-react';
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
import endorsements from '@/routes/endorsements';
import expenses from '@/routes/expenses';
import purchaseOrders from '@/routes/purchase-orders';
import sales from '@/routes/sales';
import sublimations from '@/routes/sublimations';
import type { NavItem } from '@/types';
const footerNavItems: NavItem[] = [];

export function AppSidebar() {

    const { auth } = usePage().props;
    // Define the full array inside the component or as a function
    const userRole = auth.user.role; // 'staff', 'admin', or 'superadmin'

    const isAdmin = userRole === 'admin' || userRole === 'superadmin';

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        // 1. Users/Customers: Admin/SuperAdmin only
        ...(isAdmin ? [{
            title: 'Users',
            href: '#',
            icon: Users,
            items: [
                { title: 'Users', url: '/users' },
                { title: 'Customers', url: '/customers' },
            ],
        }] : []),

        // 2. Sales: Everyone
        {
            title: 'Sales',
            href: sales.index(),
            icon: BadgeDollarSign,
        },

        // 3. Expenses & Purchase Orders: Admin/SuperAdmin only
        ...(isAdmin ? [
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
        ] : []),

        // 4. Sublimation: Everyone
        {
            title: 'Sublimation',
            href: sublimations.index(),
            icon: Shirt,
        },

        // 5. Endorsements: Admin/SuperAdmin only
        ...(isAdmin ? [{
            title: 'Endorsements',
            href: endorsements.index(),
            icon: Newspaper,
        }] : []),

        // 6. Settings: Filter specific sub-items
        ...(isAdmin ? [{
            title: 'Settings',
            href: '#',
            icon: Cog,
            items: [
                { title: 'Tags', url: '/tags' },
                // Only SuperAdmin sees Branches
                ...(userRole === 'superadmin' ? [{ title: 'Branches', url: '/branches' }] : [])
            ],
        }] : []),
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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
