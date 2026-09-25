import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    Building2,
    CalendarDays,
    LayoutDashboard,
    UserCog,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { DashboardShell } from '@/layouts/dashboard-shell';
import { dashboard, logout } from '@/routes/internal';
import { index as clientsIndex } from '@/routes/internal/clients';
import { index as eventsIndex } from '@/routes/internal/events';
import { failed } from '@/routes/internal/notifications';
import { index as staffIndex } from '@/routes/internal/staff';

type Item = {
    label: string;
    href: string;
    icon: LucideIcon;
    exact?: boolean;
    permission?: string;
};

/** Layout for the EMP staff console (/internal). */
export default function InternalLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;
    const staff = auth.staff;

    const items: Item[] = [
        {
            label: 'Dashboard',
            href: dashboard.url(),
            icon: LayoutDashboard,
            exact: true,
        },
        {
            label: 'Clients',
            href: clientsIndex.url(),
            icon: Building2,
            permission: 'clients.read',
        },
        {
            label: 'Events',
            href: eventsIndex.url(),
            icon: CalendarDays,
            permission: 'events.read',
        },
        {
            label: 'Failed messages',
            href: failed.url(),
            icon: AlertTriangle,
            permission: 'notifications.read',
        },
        {
            label: 'Staff',
            href: staffIndex.url(),
            icon: UserCog,
            permission: 'staff.read',
        },
    ];

    const nav = items.filter(
        (item) =>
            !item.permission || staff?.permissions.includes(item.permission),
    );

    return (
        <DashboardShell
            product="Internal"
            nav={nav}
            user={{
                name: staff?.name ?? '',
                subtitle: staff?.role_label ?? '',
            }}
            logoutHref={logout.url()}
        >
            {children}
        </DashboardShell>
    );
}
