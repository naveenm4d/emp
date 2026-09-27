import { Link } from '@inertiajs/react';
import { AlertTriangle, ChevronRight, Send, UserCheck } from 'lucide-react';

import { DateTile } from '@/components/shared/date-tile';
import { ResponseBar } from '@/components/shared/response-bar';
import { StatusBadge } from '@/components/shared/status-badge';
import { inDays } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show } from '@/routes/client/events';
import { index as guestsIndex } from '@/routes/client/events/guests';
import type { Event } from '@/types';

/** Guests list filtered to the parties that haven't replied yet. */
export function waitingGuestsUrl(event: Pick<Event, 'id'>): string {
    return guestsIndex.url(event.id, { query: { rsvp_status: 'pending' } });
}

/** Guests list filtered to registrations waiting for approval. */
export function toApproveGuestsUrl(event: Pick<Event, 'id'>): string {
    return guestsIndex.url(event.id, { query: { approval_status: 'pending' } });
}

/** "Cinnamon Grand · in 48 days" (registration type when there's no venue). */
export function eventSubtitle(event: Event): string {
    const where = event.location_name ?? event.registration_type_label;

    return event.event_date ? `${where} · ${inDays(event.event_date)}` : where;
}

/** Guests who answered: attending or declined. */
export function repliedCount(event: Event): number {
    return (event.attending_count ?? 0) + (event.declined_count ?? 0);
}

/**
 * Phone card for one event (4a): date tile, title, response bar, and the
 * next thing to do. Events with nobody waiting collapse to a single row.
 */
export function EventCard({
    event,
    highlight = false,
}: {
    event: Event;
    /** The nearest event: filled date tile and primary button. */
    highlight?: boolean;
}) {
    const guests = event.guests_count ?? 0;
    const waiting = event.waiting_count ?? 0;
    const toApprove = event.to_approve_count ?? 0;
    const failed = event.failed_messages_count ?? 0;
    const heading = (
        <Link
            href={show.url(event.id)}
            className="flex min-w-0 flex-1 items-center gap-3"
        >
            <DateTile
                date={event.event_date}
                variant={highlight ? 'strong' : 'outline'}
            />
            <div className="min-w-0 flex-1">
                <div className="text-[15px] font-bold">{event.title}</div>
                <div className="truncate text-xs text-muted-foreground">
                    {waiting === 0 && guests > 0
                        ? `${repliedCount(event)}/${guests} replied`
                        : eventSubtitle(event)}
                </div>
            </div>
        </Link>
    );

    if (waiting === 0) {
        return (
            <div className="flex items-center gap-3 rounded-xl bg-card p-3.5 shadow-card">
                {heading}
                {toApprove > 0 ? (
                    <Pill tone="warning" href={toApproveGuestsUrl(event)}>
                        <UserCheck /> {toApprove} to approve
                    </Pill>
                ) : failed > 0 ? (
                    <Pill tone="danger" href={waitingGuestsUrl(event)}>
                        <AlertTriangle /> {failed} failed
                    </Pill>
                ) : event.state !== 'published' ? (
                    <StatusBadge status={event.state} />
                ) : (
                    <ChevronRight className="size-4 text-subtle" />
                )}
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-3 rounded-xl bg-card p-3.5 shadow-card">
            {heading}
            <ResponseBar
                attending={event.attending_count ?? 0}
                declined={event.declined_count ?? 0}
                total={guests}
            />
            <div className="flex items-center justify-between gap-2">
                <span className="text-xs text-muted-foreground [&>b]:text-foreground">
                    <b>{repliedCount(event)}</b>/{guests} replied ·{' '}
                    <b>{waiting}</b> waiting
                </span>
                <Link
                    href={waitingGuestsUrl(event)}
                    className={cn(
                        'inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-xs font-semibold',
                        highlight
                            ? 'bg-primary text-primary-foreground'
                            : 'border border-input',
                    )}
                >
                    <Send className="size-3.5" strokeWidth={1.75} />
                    Review
                </Link>
            </div>
        </div>
    );
}

function Pill({
    tone,
    href,
    children,
}: {
    tone: 'warning' | 'danger';
    href: string;
    children: React.ReactNode;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.75 text-[11px] font-semibold [&>svg]:size-3',
                tone === 'warning'
                    ? 'bg-warning-muted text-warning'
                    : 'bg-destructive-muted text-destructive',
            )}
        >
            {children}
        </Link>
    );
}
