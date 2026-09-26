import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import { design, show } from '@/routes/client/events';
import { index as guestsIndex } from '@/routes/client/events/guests';
import { index as rsvpsIndex } from '@/routes/client/events/rsvps';
import { index as notificationsIndex } from '@/routes/client/events/notifications';
import type { Event } from '@/types';

/** Sub-navigation shared by every page that belongs to one event. */
export function EventTabs({ event }: { event: Event }) {
    const path = usePage().url.split('?')[0];

    const tabs = [
        { label: 'Overview', href: show.url(event) },
        { label: 'Design', href: design.url(event) },
        { label: 'Guests', href: guestsIndex.url(event) },
        { label: 'RSVPs', href: rsvpsIndex.url(event) },
        { label: 'Messages', href: notificationsIndex.url(event) },
    ];

    return (
        <div className="mb-6 flex gap-1 overflow-x-auto border-b border-border">
            {tabs.map((tab) => (
                <Link
                    key={tab.href}
                    href={tab.href}
                    className={cn(
                        '-mb-px border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors',
                        path === tab.href
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </div>
    );
}
