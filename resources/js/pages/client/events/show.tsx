import { Head, Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    Check,
    CircleCheck,
    Copy,
    Link2,
    ListChecks,
    Lock,
    MessageCircle,
    MessageSquareWarning,
    Palette,
    Send,
    Sofa,
    UserCheck,
    X,
} from 'lucide-react';
import { useState } from 'react';

import {
    toApproveGuestsUrl,
    waitingGuestsUrl,
} from '@/components/events/event-card';
import { EventSwitcher } from '@/components/events/event-switcher';
import { AttentionRow } from '@/components/shared/attention-row';
import { ResponseBar } from '@/components/shared/response-bar';
import { buttonVariants } from '@/components/ui/button';
import EventLayout from '@/layouts/event-layout';
import { daysUntil, formatShortDate, formatTime } from '@/lib/format';
import { invitationState, remindersUsed, showsApproval } from '@/lib/guests';
import { useClientPlan, useHasPlanFeature } from '@/lib/plans';
import { cn } from '@/lib/utils';
import { membership } from '@/routes/client';
import { design, edit, seating } from '@/routes/client/events';
import { index as notificationsIndex } from '@/routes/client/events/notifications';
import { index as rsvpsIndex } from '@/routes/client/events/rsvps';
import type {
    Event,
    Guest,
    GuestSummary,
    MessageIssues,
    Resource,
    RsvpSummary,
} from '@/types';

type Props = {
    event: Resource<Event>;
    guestSummary: GuestSummary;
    rsvpSummary: RsvpSummary;
    waiting: { data: Guest[] };
    messageIssues: MessageIssues;
    seated: number;
};

/** "62 parties haven't replied yet" (or where the event stands otherwise). */
function headline(summary: GuestSummary): string {
    if (summary.total === 0) {
        return 'No guests yet';
    }

    if (summary.rsvp_pending > 0) {
        return `${summary.rsvp_pending} ${summary.rsvp_pending === 1 ? 'party hasn’t' : 'parties haven’t'} replied yet`;
    }

    if (summary.rsvp_not_sent > 0) {
        return `${summary.rsvp_not_sent} ${summary.rsvp_not_sent === 1 ? 'invitation' : 'invitations'} to send`;
    }

    return 'Everyone has replied';
}

/** "SAT 14 NOV 2026 · 6:30 PM · CINNAMON GRAND". */
function dateLine(event: Event): string {
    return [
        event.event_date ? formatShortDate(event.event_date, true) : null,
        formatTime(event.start_time) || null,
        event.location_name,
    ]
        .filter(Boolean)
        .join(' · ');
}

/** "SAT 14 NOV · 48 DAYS TO GO". */
function countdownLine(event: Event): string | null {
    if (!event.event_date) {
        return null;
    }

    const days = daysUntil(event.event_date);
    const left =
        days > 1
            ? `${days} days to go`
            : days === 1
              ? 'tomorrow'
              : days === 0
                ? 'today'
                : 'held';

    return `${formatShortDate(event.event_date)} · ${left}`;
}

export default function ShowEvent({
    event: { data: event },
    guestSummary: summary,
    rsvpSummary,
    waiting,
    messageIssues,
    seated,
}: Props) {
    const hasFeature = useHasPlanFeature();
    const plan = useClientPlan();
    const approval = showsApproval(event, summary);
    const capacity = event.max_capacity || event.guest_limit || 0;
    const sent =
        rsvpSummary.sent +
        rsvpSummary.accepted +
        rsvpSummary.declined +
        rsvpSummary.maybe;
    const review = (
        <Link
            href={waitingGuestsUrl(event)}
            className={cn(buttonVariants(), 'h-9 rounded-md')}
        >
            <Send strokeWidth={1.75} /> Review {summary.rsvp_pending}
        </Link>
    );

    const attention = [
        approval && summary.pending > 0 && (
            <AttentionRow
                key="approve"
                icon={UserCheck}
                tone="warning"
                title={`${summary.pending} ${summary.pending === 1 ? 'registration' : 'registrations'} to approve`}
                action="Review"
                href={toApproveGuestsUrl(event)}
            />
        ),
        messageIssues.failed > 0 && (
            <AttentionRow
                key="failed"
                icon={MessageSquareWarning}
                tone="danger"
                title={`${messageIssues.failed} ${messageIssues.failed === 1 ? 'invitation' : 'invitations'} failed`}
                action="Fix"
                href={waitingGuestsUrl(event)}
            />
        ),
    ].filter(Boolean);

    return (
        <EventLayout event={event}>
            <Head title={event.title} />

            {/* Phones (4b): hero with the switcher, figures card, tiles. */}
            <div className="md:hidden">
                <div className="-mx-3 -mt-4 flex flex-col gap-4.5 bg-sidebar px-4 pt-5 pb-18 text-sidebar-foreground">
                    <EventSwitcher
                        event={event}
                        className="w-fit bg-sidebar-foreground/12"
                    />
                    <div>
                        {countdownLine(event) && (
                            <div className="text-xs font-bold tracking-[0.08em] text-link uppercase">
                                {countdownLine(event)}
                            </div>
                        )}
                        <h1 className="mt-1.5 text-[28px] leading-tight font-bold">
                            {headline(summary)}
                        </h1>
                    </div>
                </div>

                <div className="relative -mt-13 flex flex-col gap-3 rounded-2xl bg-card p-3.5 shadow-[0_6px_20px_rgba(0,0,0,0.14)]">
                    <div className="grid grid-cols-3 text-center">
                        <Figure
                            value={summary.rsvp_confirmed}
                            label="Attending"
                            icon={Check}
                            className="text-success"
                        />
                        <Figure
                            value={summary.rsvp_declined}
                            label="Declined"
                            icon={X}
                            className="border-x border-border text-destructive"
                        />
                        <Figure
                            value={summary.headcount}
                            label="Headcount"
                            className="text-muted-foreground"
                        />
                    </div>
                    {summary.rsvp_pending > 0 && (
                        <Link
                            href={waitingGuestsUrl(event)}
                            className="flex h-12 items-center justify-center gap-2 rounded-lg bg-primary text-[15px] font-semibold text-primary-foreground"
                        >
                            <Send className="size-4.5" strokeWidth={1.75} />
                            Review {summary.rsvp_pending} waiting
                        </Link>
                    )}
                    <div className="text-center text-xs text-muted-foreground">
                        {sent} invitations sent · {rsvpSummary.pending} not sent
                    </div>
                </div>

                {event.public_url && (
                    <div className="mt-3 rounded-xl bg-card shadow-card">
                        <PublicLinkRow url={event.public_url} />
                    </div>
                )}

                <div className="grid grid-cols-2 gap-2.5 pt-3">
                    {approval && (
                        <Tile
                            icon={UserCheck}
                            title="Approvals"
                            href={toApproveGuestsUrl(event)}
                            detail={`${summary.pending} waiting`}
                            tone={summary.pending > 0 ? 'warning' : undefined}
                        />
                    )}
                    <Tile
                        icon={ListChecks}
                        title="RSVPs"
                        href={rsvpsIndex.url(event.id)}
                        detail={`${rsvpSummary.accepted} accepted · ${rsvpSummary.sent} awaiting`}
                        locked={!hasFeature('rsvp_list')}
                    />
                    <Tile
                        icon={MessageCircle}
                        title="Messages"
                        href={notificationsIndex.url(event.id)}
                        detail={
                            messageIssues.failed > 0
                                ? `${messageIssues.failed} failed`
                                : `${sent} sent`
                        }
                        tone={messageIssues.failed > 0 ? 'danger' : undefined}
                        locked={!hasFeature('message_log')}
                    />
                    <Tile
                        icon={Palette}
                        title="Invitation"
                        href={design.url(event.id)}
                        detail={`${event.template?.name ?? 'Template'} · ${event.state === 'published' ? 'live' : event.state}`}
                    />
                    <Tile
                        icon={Sofa}
                        title="Seating"
                        href={seating.url(event.id)}
                        detail={`${seated} of ${summary.headcount} seated`}
                        locked={!hasFeature('seating')}
                    />
                </div>
            </div>

            {/* Tablets and desktops (4b). */}
            <div className="hidden gap-5 md:grid lg:grid-cols-[minmax(0,1fr)_380px]">
                <div className="flex min-w-0 flex-col gap-4">
                    <div>
                        <div className="text-xs font-bold tracking-[0.08em] text-link uppercase">
                            {dateLine(event)}
                        </div>
                        <h1 className="mt-1 text-[28px] font-bold">
                            {headline(summary)}
                        </h1>
                    </div>

                    {event.public_url && (
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl bg-card px-4 py-3 shadow-card">
                            <span className="flex items-center gap-2 text-sm font-bold">
                                <Link2
                                    className="size-4.5"
                                    strokeWidth={1.75}
                                />
                                Public link
                            </span>
                            <div className="min-w-60 flex-1">
                                <CopyField url={event.public_url} />
                            </div>
                            <span className="text-xs text-muted-foreground">
                                Opened {event.public_link_open_count ?? 0} times
                            </span>
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                        <Stat
                            label="Attending"
                            value={summary.rsvp_confirmed}
                            detail="parties"
                            className="text-success"
                        />
                        <Stat
                            label="Declined"
                            value={summary.rsvp_declined}
                            detail="parties"
                            className="text-destructive"
                        />
                        <Stat
                            label="Headcount"
                            value={summary.headcount}
                            detail={`${summary.rsvp_confirmed} guests · ${summary.confirmed_children} children · ${summary.confirmed_additional} +1`}
                        />
                        {capacity > 0 ? (
                            <Stat
                                label="Capacity"
                                value={`${Math.round((summary.headcount_total / capacity) * 100)}%`}
                                detail={`${summary.headcount_total} of ${capacity}`}
                            />
                        ) : (
                            <Stat
                                label="Guests"
                                value={summary.total}
                                detail="no capacity limit"
                            />
                        )}
                    </div>

                    <div className="overflow-hidden rounded-xl bg-card shadow-card">
                        <div className="flex items-center gap-3 border-b border-border px-4 py-3.5">
                            <span className="flex-1 text-[15px] font-bold">
                                Waiting on reply
                            </span>
                            {summary.rsvp_pending > 0 && review}
                        </div>
                        {waiting.data.length === 0 ? (
                            <p className="px-4 py-6 text-sm text-muted-foreground">
                                Nobody is waiting on a reply.
                            </p>
                        ) : (
                            <table className="w-full border-collapse text-[13px]">
                                <tbody>
                                    {waiting.data.map((guest) => (
                                        <WaitingRow
                                            key={guest.id}
                                            guest={guest}
                                            event={event}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        )}
                        {summary.rsvp_pending > waiting.data.length && (
                            <Link
                                href={waitingGuestsUrl(event)}
                                className="block border-t border-border px-4 py-2.5 text-xs font-semibold text-link"
                            >
                                See all {summary.rsvp_pending}
                            </Link>
                        )}
                    </div>

                    <div className="rounded-xl bg-card p-4 shadow-card">
                        <div className="mb-2 flex justify-between text-xs">
                            <span className="text-muted-foreground">
                                Responses
                            </span>
                            <span className="font-bold">
                                {summary.rsvp_confirmed + summary.rsvp_declined}{' '}
                                of {summary.total} parties
                            </span>
                        </div>
                        <ResponseBar
                            attending={summary.rsvp_confirmed}
                            declined={summary.rsvp_declined}
                            total={summary.total}
                            legend={{ waiting: summary.rsvp_pending }}
                        />
                    </div>
                </div>

                <aside className="flex flex-col gap-3">
                    {event.state !== 'published' && (
                        <div className="rounded-xl bg-card p-4 text-sm shadow-card">
                            <p className="font-bold">
                                {event.state === 'draft'
                                    ? 'This event is a draft'
                                    : 'This event was cancelled'}
                            </p>
                            <p className="mt-1 text-muted-foreground">
                                {event.state === 'draft'
                                    ? 'Guests can’t open its page until you publish it.'
                                    : 'Guests can no longer register or reply.'}
                            </p>
                            {event.state === 'draft' && (
                                <Link
                                    href={edit.url(event.id)}
                                    className="mt-2 inline-block text-xs font-bold text-link"
                                >
                                    Publish in Settings
                                </Link>
                            )}
                        </div>
                    )}

                    {attention.length > 0 && (
                        <div className="overflow-hidden rounded-xl bg-card shadow-card">
                            <div className="border-b border-border px-4 py-3.5 text-sm font-bold">
                                Needs attention
                            </div>
                            {attention}
                        </div>
                    )}

                    {plan?.plan === 'starter' && (
                        <p className="rounded-xl bg-card px-4 py-3.5 text-sm text-muted-foreground shadow-card">
                            You’re on the free Starter plan: up to{' '}
                            {plan.max_guests_per_event} guests and 1 invitation
                            + 1 reminder per guest.{' '}
                            <Link
                                href={membership.url()}
                                className="font-semibold text-link hover:underline"
                            >
                                Upgrade to Celebration
                            </Link>{' '}
                            for public registration, seating, RSVP tracking and
                            the message log.
                        </p>
                    )}
                </aside>
            </div>
        </EventLayout>
    );
}

function Figure({
    value,
    label,
    icon: Icon,
    className,
}: {
    value: number;
    label: string;
    icon?: LucideIcon;
    className?: string;
}) {
    return (
        <div className={className}>
            <div className="text-xl font-bold text-foreground">{value}</div>
            <div className="flex items-center justify-center gap-0.75 text-[11px] font-semibold">
                {Icon && <Icon className="size-3" />}
                {label}
            </div>
        </div>
    );
}

function Stat({
    label,
    value,
    detail,
    className = 'text-muted-foreground',
}: {
    label: string;
    value: number | string;
    detail: string;
    className?: string;
}) {
    return (
        <div className="rounded-xl bg-card p-4 shadow-card">
            <div className={cn('text-xs font-semibold', className)}>
                {label}
            </div>
            <div className="text-[28px] leading-tight font-bold tabular-nums">
                {value}
            </div>
            <div className="text-xs text-subtle">{detail}</div>
        </div>
    );
}

function Tile({
    icon: Icon,
    title,
    detail,
    href,
    tone,
    locked = false,
}: {
    icon: LucideIcon;
    title: string;
    detail: string;
    href: string;
    tone?: 'warning' | 'danger';
    /** Not in the client's plan: the page behind it shows an upgrade prompt. */
    locked?: boolean;
}) {
    return (
        <Link
            href={href}
            className="flex flex-col gap-2.5 rounded-xl bg-card p-3.5 shadow-card"
        >
            <div className="flex items-start justify-between">
                <Icon
                    className={cn('size-6', locked && 'text-subtle')}
                    strokeWidth={1.75}
                />
                {locked && (
                    <Lock
                        aria-label="Upgrade to unlock"
                        className="size-4 text-subtle"
                    />
                )}
            </div>
            <div>
                <div className="text-sm font-bold">{title}</div>
                <div
                    className={cn(
                        'truncate text-xs',
                        !locked &&
                            tone === 'warning' &&
                            'font-semibold text-warning',
                        !locked &&
                            tone === 'danger' &&
                            'font-semibold text-destructive',
                        (locked || !tone) && 'text-muted-foreground',
                    )}
                >
                    {locked ? 'Upgrade to unlock' : detail}
                </div>
            </div>
        </Link>
    );
}

function WaitingRow({ guest, event }: { guest: Guest; event: Event }) {
    const state = invitationState(guest);
    const reminders = remindersUsed(guest, event.message_limits);

    return (
        <tr className="border-t border-border first:border-t-0 even:bg-raised">
            <td className="px-4 py-2.5 font-semibold">{guest.name}</td>
            <td className="p-2.5 text-muted-foreground">
                {guest.phone ?? guest.email}
            </td>
            <td
                className={cn(
                    'p-2.5',
                    state.failed
                        ? 'font-semibold text-destructive'
                        : 'text-muted-foreground',
                )}
            >
                {state.failed && (
                    <AlertTriangle className="mr-1 inline size-3.5 align-[-2px]" />
                )}
                {state.label}
            </td>
            <td
                className={cn(
                    'p-2.5 pr-4',
                    reminders.atLimit ? 'text-subtle' : 'text-muted-foreground',
                )}
            >
                {reminders.label}
            </td>
        </tr>
    );
}

function CopyField({ url }: { url: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <div className="flex gap-2">
            <div className="flex h-9 min-w-0 flex-1 items-center truncate rounded-md border border-input px-2.5 text-[13px]">
                {url.replace(/^https?:\/\//, '')}
            </div>
            <button
                type="button"
                title="Copy public link"
                onClick={() => {
                    void navigator.clipboard.writeText(url);
                    setCopied(true);
                }}
                className="flex size-9 items-center justify-center rounded-md border border-input hover:bg-raised"
            >
                {copied ? (
                    <CircleCheck className="size-4 text-success" />
                ) : (
                    <Copy className="size-4" />
                )}
            </button>
        </div>
    );
}

function PublicLinkRow({ url }: { url: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <div className="flex items-center gap-3 p-3.5">
            <Link2 className="size-5.5 shrink-0" strokeWidth={1.75} />
            <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-semibold">
                    {url.replace(/^https?:\/\//, '')}
                </div>
                <div className="text-xs text-subtle">Public event page</div>
            </div>
            <button
                type="button"
                onClick={() => {
                    void navigator.clipboard.writeText(url);
                    setCopied(true);
                }}
                className="inline-flex h-8 items-center gap-1.5 rounded-full border border-input px-3 text-xs font-semibold"
            >
                {copied ? (
                    <CircleCheck className="size-3.5 text-success" />
                ) : (
                    <Copy className="size-3.5" />
                )}
                {copied ? 'Copied' : 'Copy'}
            </button>
        </div>
    );
}
