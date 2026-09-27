import { usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Gem,
    House,
    LayoutTemplate,
    Plus,
    UserCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { BottomTabBar } from '@/components/shared/bottom-tab-bar';
import { CommandPalette } from '@/components/shared/command-palette';
import type { NavItem } from '@/layouts/dashboard-shell';
import { DashboardShell, isNavActive } from '@/layouts/dashboard-shell';
import { useClientPlan } from '@/lib/plans';
import { dashboard, logout, membership } from '@/routes/client';
import { create, index as eventsIndex } from '@/routes/client/events';
import { edit as profileEdit } from '@/routes/client/profile';
import { index as templatesIndex } from '@/routes/client/templates';

type ClientLayoutProps = {
    children: ReactNode;
    /** Replaces the app tab bar on phones (event pages bring their own). */
    bottomBar?: ReactNode;
    /** Bar above the content from `md` up, in place of the sidebar. */
    header?: ReactNode;
};

/** Layout for the subscribed client's dashboard (/app). */
export default function ClientLayout({
    children,
    bottomBar,
    header,
}: ClientLayoutProps) {
    const page = usePage();
    const { auth } = page.props;
    const path = page.url.split('?')[0];
    const plan = useClientPlan();

    const nav: NavItem[] = [
        { label: 'Home', href: dashboard.url(), icon: House, exact: true },
        { label: 'Events', href: eventsIndex.url(), icon: CalendarDays },
        {
            label: 'Templates',
            href: templatesIndex.url(),
            icon: LayoutTemplate,
        },
        { label: 'Account', href: profileEdit.url(), icon: UserCircle },
    ];
    // On phones Membership is reached from Account, so the tab bar keeps four tabs.
    const sidebarNav: NavItem[] = [
        ...nav.slice(0, 3),
        {
            label: 'Membership',
            shortLabel: 'Plan',
            href: membership.url(),
            icon: Gem,
        },
        ...nav.slice(3),
    ];

    return (
        <DashboardShell
            nav={sidebarNav}
            user={{
                name: auth.client?.name ?? '',
                subtitle: auth.client?.email ?? '',
            }}
            logoutHref={logout.url()}
            accountHref={profileEdit.url()}
            header={header}
            hideNav={!!header}
            bottomBar={
                bottomBar ?? (
                    <BottomTabBar
                        items={nav.map((item) => ({
                            label: item.label,
                            href: item.href,
                            icon: item.icon,
                            active: isNavActive(path, item),
                        }))}
                        fab={{
                            label: 'New event',
                            icon: Plus,
                            href: create.url(),
                            disabled: !!plan && !plan.can_create_event,
                            title: plan?.reason ?? undefined,
                        }}
                    />
                )
            }
        >
            {children}
            <CommandPalette />
        </DashboardShell>
    );
}
