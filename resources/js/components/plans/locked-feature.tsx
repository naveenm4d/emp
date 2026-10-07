import { Head, Link } from '@inertiajs/react';
import { Lock, Sparkles } from 'lucide-react';
import type { ReactNode } from 'react';

import { PageHeader } from '@/components/shared/page-header';
import { buttonVariants } from '@/components/ui/button';
import EventLayout from '@/layouts/event-layout';
import { cn } from '@/lib/utils';
import { membership } from '@/routes/client';
import type { Event, PlanFeature } from '@/types';

const pitches: Record<
    Exclude<PlanFeature, 'public_registration'>,
    { title: string; body: string }
> = {
    seating: {
        title: 'Seating plans are on Celebration and up',
        body: 'Lay out your tables, seat every party together and see who’s confirmed at each table.',
    },
    rsvp_list: {
        title: 'RSVP tracking is on Celebration and up',
        body: 'Follow every RSVP link: sent, delivered, read and answered, in one list.',
    },
    message_log: {
        title: 'The message log is on Celebration and up',
        body: 'See every WhatsApp invitation and reminder, with delivery and read receipts.',
    },
};

/**
 * An event page the client's plan doesn't include: the tab stays, and its
 * content is a blurred sample (never the event's data) under an upgrade prompt.
 */
export function LockedEventPage({
    event,
    title,
    feature,
    preview,
}: {
    event: Event;
    title: string;
    feature: keyof typeof pitches;
    /** Static sample markup hinting at the page; it's blurred and inert. */
    preview: ReactNode;
}) {
    const pitch = pitches[feature];

    return (
        <EventLayout event={event}>
            <Head title={`${title} · ${event.title}`} />
            <PageHeader eyebrow={event.title} title={title} />

            <div className="relative min-h-[420px] overflow-hidden rounded-2xl">
                <div
                    aria-hidden
                    inert
                    className="pointer-events-none opacity-85 blur-[2px] select-none"
                >
                    {preview}
                </div>
                <div className="absolute inset-0 flex items-center justify-center bg-radial from-background/70 via-background/30 to-transparent p-4">
                    <div className="flex max-w-sm flex-col items-center gap-3 rounded-2xl bg-card p-6 text-center shadow-card">
                        <span className="flex size-11 items-center justify-center rounded-full bg-strong text-strong-foreground">
                            <Lock className="size-5" strokeWidth={2} />
                        </span>
                        <h2 className="text-base font-bold">{pitch.title}</h2>
                        <p className="text-sm text-muted-foreground">
                            {pitch.body}
                        </p>
                        <Link
                            href={membership.url()}
                            className={cn(
                                buttonVariants(),
                                'mt-1 rounded-full',
                            )}
                        >
                            <Sparkles /> View membership
                        </Link>
                    </div>
                </div>
            </div>
        </EventLayout>
    );
}
