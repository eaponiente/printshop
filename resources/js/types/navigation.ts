import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href'] | string>;
    url?: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    badge?: number;
    items?: {
        title: string;
        url: string;
    }[];
};

export type NavGroup = {
    label?: string; // omit for the top (Dashboard) group
    items: NavItem[];
    visible?: boolean; // defaults to visible when omitted
};
