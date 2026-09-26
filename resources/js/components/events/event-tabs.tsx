import { Link, usePage } from '@inertiajs/react';

import { useHasPlanFeature } from '@/lib/plans';
import { cn } from '@/lib/utils';
import { design, seating, settings, show } from '@/routes/client/events';
import { index as guestsIndex } from '@/routes/client/events/guests';
import { index as rsvpsIndex } from '@/routes/client/events/rsvps';
import { index as notificationsIndex } from '@/routes/client/events/notifications';
import type { Event, PlanFeature } from '@/types';

/** Sub-navigation shared by every page that belongs to one event. */
export function EventTabs({ event }: { event: Event }) {
    const path = usePage().url.split('?')[0];
    // Seating, RSVPs and Messages aren't part of the Starter plan.
    const hasFeature = useHasPlanFeature();

    type Tab = { label: string; href: string; feature?: PlanFeature };

    const all: Tab[] = [
        { label: 'Overview', href: show.url(event) },
        { label: 'Design', href: design.url(event) },
        { label: 'Settings', href: settings.url(event) },
        { label: 'Guests', href: guestsIndex.url(event) },
        { label: 'Seating', href: seating.url(event), feature: 'seating' },
        { label: 'RSVPs', href: rsvpsIndex.url(event), feature: 'rsvp_list' },
        {
            label: 'Messages',
            href: notificationsIndex.url(event),
            feature: 'message_log',
        },
    ];
    const tabs = all.filter((tab) => !tab.feature || hasFeature(tab.feature));

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
