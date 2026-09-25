import { usePage } from '@inertiajs/react';
import { CalendarDays, UserCircle } from 'lucide-react';
import type { ReactNode } from 'react';

import { DashboardShell } from '@/layouts/dashboard-shell';
import { logout } from '@/routes/client';
import { index as eventsIndex } from '@/routes/client/events';
import { edit as profileEdit } from '@/routes/client/profile';

/** Layout for the subscribed client's dashboard (/app). */
export default function ClientLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;

    return (
        <DashboardShell
            product="Dashboard"
            nav={[
                {
                    label: 'Events',
                    href: eventsIndex.url(),
                    icon: CalendarDays,
                },
                { label: 'Profile', href: profileEdit.url(), icon: UserCircle },
            ]}
            user={{
                name: auth.client?.name ?? '',
                subtitle: auth.client?.email ?? '',
            }}
            logoutHref={logout.url()}
        >
            {children}
        </DashboardShell>
    );
}
