import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    Building2,
    CalendarDays,
    History,
    LayoutDashboard,
    LayoutTemplate,
    UserCog,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { DashboardShell } from '@/layouts/dashboard-shell';
import { useStaffCan } from '@/lib/permissions';
import { dashboard, logout } from '@/routes/admin';
import { index as activityIndex } from '@/routes/admin/activity';
import { index as clientsIndex } from '@/routes/admin/clients';
import { index as eventsIndex } from '@/routes/admin/events';
import { failed } from '@/routes/admin/notifications';
import { index as staffIndex } from '@/routes/admin/staff';
import { index as templatesIndex } from '@/routes/admin/templates';

type Item = {
    label: string;
    href: string;
    icon: LucideIcon;
    exact?: boolean;
    permission?: string;
};

/** Layout for the EMP staff console (/admin). */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;
    const staff = auth.staff;
    const can = useStaffCan();

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
            label: 'Templates',
            href: templatesIndex.url(),
            icon: LayoutTemplate,
            permission: 'templates.read',
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
        {
            label: 'Activity',
            href: activityIndex.url(),
            icon: History,
            permission: 'activity.read',
        },
    ];

    const nav = items.filter(
        (item) => !item.permission || can(item.permission),
    );

    return (
        <DashboardShell
            product="Admin"
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
