import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ClipboardList,
    LayoutGrid,
    ListChecks,
    MessageCircle,
    Palette,
    Settings,
    Sofa,
    Users,
} from 'lucide-react';

import { useClientCan } from '@/lib/permissions';
import { useHasPlanFeature } from '@/lib/plans';
import { design, edit, seating, settings, show } from '@/routes/client/events';
import { index as guestsIndex } from '@/routes/client/events/guests';
import { index as notificationsIndex } from '@/routes/client/events/notifications';
import { index as rsvpsIndex } from '@/routes/client/events/rsvps';
import type { Event, PlanFeature } from '@/types';

export type EventTab = {
    label: string;
    href: string;
    icon: LucideIcon;
    active: boolean;
    /** Kept on the phone tab bar; the rest go under "More". */
    primary?: boolean;
    /** Not in the client's plan: the tab stays, its page shows an upgrade prompt. */
    locked: boolean;
};

/**
 * The pages of one event, in the order of the event header (4b). Seating,
 * RSVPs and Messages aren't part of the Starter plan: their tabs are locked.
 */
export function useEventTabs(event: Pick<Event, 'id'>): EventTab[] {
    const path = usePage().url.split('?')[0];
    const hasFeature = useHasPlanFeature();
    const can = useClientCan();

    const all: (Omit<EventTab, 'active' | 'locked'> & {
        feature?: PlanFeature;
    })[] = [
        {
            label: 'Overview',
            href: show.url(event.id),
            icon: LayoutGrid,
            primary: true,
        },
        {
            label: 'Guests',
            href: guestsIndex.url(event.id),
            icon: Users,
            primary: true,
        },
        {
            label: 'RSVPs',
            href: rsvpsIndex.url(event.id),
            icon: ListChecks,
            feature: 'rsvp_list',
        },
        {
            label: 'RSVP form',
            href: settings.url(event.id),
            icon: ClipboardList,
        },
        {
            label: 'Messages',
            href: notificationsIndex.url(event.id),
            icon: MessageCircle,
            feature: 'message_log',
            primary: true,
        },
        { label: 'Design', href: design.url(event.id), icon: Palette },
        {
            label: 'Seating',
            href: seating.url(event.id),
            icon: Sofa,
            feature: 'seating',
            primary: true,
        },
        // Event details need events.update; team members without it don't get the tab.
        ...(can('events.update')
            ? [{ label: 'Settings', href: edit.url(event.id), icon: Settings }]
            : []),
    ];

    return all.map(({ feature, ...tab }) => ({
        ...tab,
        active: path === tab.href,
        locked: !!feature && !hasFeature(feature),
    }));
}
