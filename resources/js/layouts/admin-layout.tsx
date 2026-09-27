import { router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    Bell,
    Building2,
    CalendarDays,
    LayoutDashboard,
    LayoutTemplate,
    Search,
    Shield,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { DashboardShell } from '@/layouts/dashboard-shell';
import { APP_TIME_ZONE } from '@/lib/format';
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
    badge?: number;
    shortLabel?: string;
};

/** Layout for the EMP staff console (/admin). */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth, failedMessages } = usePage<{
        failedMessages: number | null;
    }>().props;
    const staff = auth.staff;
    const can = useStaffCan();

    const items: Item[] = [
        {
            label: 'Overview',
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
            label: 'Notifications',
            href: failed.url(),
            icon: Bell,
            permission: 'notifications.read',
            badge: failedMessages ?? undefined,
            shortLabel: 'Alerts',
        },
        {
            label: 'Activity',
            href: activityIndex.url(),
            icon: Activity,
            permission: 'activity.read',
        },
        {
            label: 'Staff',
            href: staffIndex.url(),
            icon: Shield,
            permission: 'staff.read',
        },
    ];

    const nav = items.filter(
        (item) => !item.permission || can(item.permission),
    );

    return (
        <DashboardShell
            badge="STAFF"
            nav={nav}
            header={<ConsoleHeader canSearch={can('events.read')} />}
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

/** Bar above every console page (3a): event search, time zone and date. */
function ConsoleHeader({ canSearch }: { canSearch: boolean }) {
    const today = new Date().toLocaleDateString('en-GB', {
        timeZone: APP_TIME_ZONE,
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

    return (
        <header className="hidden h-16 items-center gap-4 border-b border-border bg-card px-6 md:flex">
            {canSearch && (
                <form
                    className="relative max-w-105 flex-1"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const search = new FormData(e.currentTarget).get(
                            'search',
                        );
                        router.get(eventsIndex.url(), {
                            search: search || null,
                        });
                    }}
                >
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                    <input
                        name="search"
                        type="search"
                        placeholder="Search events"
                        className="h-9.5 w-full rounded-md bg-background pr-3 pl-9 text-[13px] outline-none placeholder:text-subtle focus-visible:ring-3 focus-visible:ring-ring/50"
                    />
                </form>
            )}
            <div className="ml-auto text-xs text-muted-foreground">
                {APP_TIME_ZONE} · {today}
            </div>
        </header>
    );
}
