import { Head, Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    ArrowRight,
    Ban,
    CalendarDays,
    Check,
    CircleCheck,
    ClipboardList,
    Copy,
    ExternalLink,
    Hourglass,
    Link2,
    ListChecks,
    Lock,
    MapPin,
    MessageCircle,
    MessageSquareWarning,
    Palette,
    PencilLine,
    Send,
    Sofa,
    Sparkles,
    UserCheck,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';

import {
    toApproveGuestsUrl,
    waitingGuestsUrl,
} from '@/components/events/event-card';
import { EventPills } from '@/components/events/event-pills';
import { EventSwitcher } from '@/components/events/event-switcher';
import { AttentionRow } from '@/components/shared/attention-row';
import { ResponseBar } from '@/components/shared/response-bar';
import { buttonVariants } from '@/components/ui/button';
import EventLayout from '@/layouts/event-layout';
import { formatShortDate, formatTime, initials } from '@/lib/format';
import { invitationState, remindersUsed, showsApproval } from '@/lib/guests';
import { useClientCan } from '@/lib/permissions';
import { useClientPlan, useHasPlanFeature } from '@/lib/plans';
import { cn } from '@/lib/utils';
import { membership } from '@/routes/client';
import { design, edit, seating, settings } from '@/routes/client/events';
import { index as guestsIndex } from '@/routes/client/events/guests';
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
    const canUpdate = useClientCan()('events.update');
    const approval = showsApproval(event, summary);
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
        <EventLayout event={event} headline={headline(summary)}>
            <Head title={event.title} />

            {/* Phones: the invitation as a poster, the replies card, tiles and lists. */}
            <div className="flex flex-col gap-4 md:hidden">
                <PhonePoster event={event} />

                <div className="relative -mt-14 flex flex-col gap-3.5 rounded-3xl bg-card p-4 shadow-[0_10px_30px_rgba(0,0,0,0.18)]">
                    <div className="flex items-center gap-4">
                        <RepliesRing
                            attending={summary.rsvp_confirmed}
                            declined={summary.rsvp_declined}
                            total={summary.total}
                        />
                        <div className="grid flex-1 grid-cols-3 gap-2 text-center">
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
                                className="text-destructive"
                            />
                            <Figure
                                value={summary.headcount}
                                label="Headcount"
                                icon={Users}
                                className="text-info"
                            />
                        </div>
                    </div>
                    <p className="text-center text-xs text-muted-foreground">
                        {headline(summary)} · {sent} sent ·{' '}
                        {rsvpSummary.pending} not sent
                    </p>
                    {summary.rsvp_pending > 0 && (
                        <Link
                            href={waitingGuestsUrl(event)}
                            className="flex h-12 items-center justify-center gap-2 rounded-full bg-primary text-[15px] font-semibold text-primary-foreground"
                        >
                            <Send className="size-4.5" strokeWidth={1.75} />
                            Review {summary.rsvp_pending} waiting
                        </Link>
                    )}
                </div>

                {event.public_url && (
                    <div className="rounded-2xl bg-card shadow-card">
                        <PublicLinkRow url={event.public_url} />
                    </div>
                )}

                {attention.length > 0 && (
                    <div className="overflow-hidden rounded-2xl bg-card shadow-card">
                        {attention}
                    </div>
                )}

                {/* Always the same six tiles; approvals show on the Guests tile. */}
                <div className="grid grid-cols-2 gap-3">
                    <Tile
                        icon={Users}
                        title="Guests"
                        href={
                            approval && summary.pending > 0
                                ? toApproveGuestsUrl(event)
                                : guestsIndex.url(event.id)
                        }
                        detail={
                            approval && summary.pending > 0
                                ? `${summary.pending} to approve`
                                : `${summary.total} on the list`
                        }
                        tone={
                            approval && summary.pending > 0
                                ? 'warning'
                                : undefined
                        }
                        color="bg-info-muted text-info"
                    />
                    <Tile
                        icon={ListChecks}
                        title="RSVPs"
                        href={rsvpsIndex.url(event.id)}
                        detail={`${rsvpSummary.accepted} accepted · ${rsvpSummary.sent} awaiting`}
                        locked={!hasFeature('rsvp_list')}
                        color="bg-success-muted text-success"
                    />
                    <Tile
                        icon={ClipboardList}
                        title="RSVP form"
                        href={settings.url(event.id)}
                        detail="What guests are asked"
                        color="bg-warning-muted text-warning"
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
                        color="bg-accent text-accent-foreground"
                    />
                    <Tile
                        icon={Palette}
                        title="Invitation"
                        href={design.url(event.id)}
                        detail={event.template?.name ?? 'Choose a design'}
                        color="bg-destructive-muted text-destructive"
                    />
                    <Tile
                        icon={Sofa}
                        title="Seating"
                        href={seating.url(event.id)}
                        detail={`${seated} of ${summary.headcount} seated`}
                        locked={!hasFeature('seating')}
                        color="bg-foreground/6 text-foreground"
                    />
                </div>

                {waiting.data.length > 0 && (
                    <section className="overflow-hidden rounded-2xl bg-card shadow-card">
                        <div className="flex items-center justify-between px-4 pt-4 pb-1">
                            <h2 className="text-sm font-bold">
                                Waiting on reply
                            </h2>
                            <Link
                                href={waitingGuestsUrl(event)}
                                className="text-xs font-semibold text-link"
                            >
                                See all {summary.rsvp_pending}
                            </Link>
                        </div>
                        <ul className="px-1 pb-1">
                            {waiting.data.slice(0, 3).map((guest) => (
                                <WaitingRow
                                    key={guest.id}
                                    guest={guest}
                                    event={event}
                                />
                            ))}
                        </ul>
                    </section>
                )}

                {plan?.plan === 'starter' && (
                    <Link
                        href={membership.url()}
                        className="flex items-center gap-3 rounded-2xl bg-hero p-4 text-hero-foreground ring-1 ring-hero-edge"
                    >
                        <Sparkles className="size-5 shrink-0 text-hero-accent" />
                        <span className="flex-1 text-sm">
                            <span className="block font-bold">
                                Free Starter plan
                            </span>
                            <span className="block text-xs opacity-75">
                                Upgrade for seating, RSVPs and messages
                            </span>
                        </span>
                        <ArrowRight className="size-4" />
                    </Link>
                )}
            </div>

            {/* Tablets and desktops. */}
            <div className="hidden flex-col gap-6 md:flex">
                <PublicLinkBar event={event} />

                <div className="grid grid-cols-1 items-start gap-6 *:min-w-0 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="flex flex-col gap-6">
                        <Pipeline rsvpSummary={rsvpSummary} summary={summary} />

                        <section className="overflow-hidden rounded-3xl bg-card shadow-card">
                            <div className="flex items-center gap-3 px-5 pt-5 pb-3">
                                <span className="flex size-9 items-center justify-center rounded-xl bg-warning-muted text-warning">
                                    <Hourglass className="size-4.5" />
                                </span>
                                <div className="flex-1">
                                    <h2 className="text-[15px] font-bold">
                                        Waiting on reply
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        Invited, no answer yet
                                    </p>
                                </div>
                                {summary.rsvp_pending > 0 && review}
                            </div>
                            {waiting.data.length === 0 ? (
                                <div className="flex items-center gap-3 px-5 pt-1 pb-5 text-sm text-muted-foreground">
                                    <CircleCheck className="size-5 text-success" />
                                    Nobody is waiting on a reply.
                                </div>
                            ) : (
                                <ul className="px-2 pb-2">
                                    {waiting.data.map((guest) => (
                                        <WaitingRow
                                            key={guest.id}
                                            guest={guest}
                                            event={event}
                                        />
                                    ))}
                                </ul>
                            )}
                            {summary.rsvp_pending > waiting.data.length && (
                                <Link
                                    href={waitingGuestsUrl(event)}
                                    className="flex items-center justify-center gap-1 border-t border-border px-4 py-3 text-xs font-semibold text-link hover:bg-raised"
                                >
                                    See all {summary.rsvp_pending}{' '}
                                    <ArrowRight className="size-3.5" />
                                </Link>
                            )}
                        </section>
                    </div>

                    <aside className="flex flex-col gap-4">
                        {event.state !== 'published' && (
                            <div
                                className={cn(
                                    'flex gap-3 rounded-2xl p-4 text-sm',
                                    event.state === 'draft'
                                        ? 'bg-warning-muted'
                                        : 'bg-destructive-muted',
                                )}
                            >
                                {event.state === 'draft' ? (
                                    <PencilLine className="mt-0.5 size-4.5 shrink-0 text-warning" />
                                ) : (
                                    <Ban className="mt-0.5 size-4.5 shrink-0 text-destructive" />
                                )}
                                <div>
                                    <p className="font-bold">
                                        {event.state === 'draft'
                                            ? 'This event is a draft'
                                            : 'This event was cancelled'}
                                    </p>
                                    <p className="mt-0.5 text-muted-foreground">
                                        {event.state === 'draft'
                                            ? 'Guests can’t open its page until you publish it.'
                                            : 'Guests can no longer register or reply.'}
                                    </p>
                                    {event.state === 'draft' && canUpdate && (
                                        <Link
                                            href={edit.url(event.id)}
                                            className="mt-2 inline-flex items-center gap-1 text-xs font-bold text-link"
                                        >
                                            Publish in Settings{' '}
                                            <ArrowRight className="size-3.5" />
                                        </Link>
                                    )}
                                </div>
                            </div>
                        )}

                        {attention.length > 0 ? (
                            <div className="overflow-hidden rounded-2xl bg-card shadow-card">
                                <div className="border-b border-border px-4 py-3.5 text-sm font-bold">
                                    Needs attention
                                </div>
                                {attention}
                            </div>
                        ) : (
                            event.state === 'published' && (
                                <div className="flex items-center gap-3 rounded-2xl bg-card p-4 shadow-card">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-success-muted text-success">
                                        <CircleCheck className="size-5" />
                                    </span>
                                    <div>
                                        <p className="text-sm font-bold">
                                            All caught up
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            No approvals waiting, no failed
                                            messages.
                                        </p>
                                    </div>
                                </div>
                            )
                        )}

                        <section className="rounded-2xl bg-card p-2 shadow-card">
                            <h2 className="px-2.5 pt-2 pb-1.5 text-sm font-bold">
                                Jump to
                            </h2>
                            <div className="grid grid-cols-2 gap-1">
                                <Shortcut
                                    icon={Users}
                                    title="Guests"
                                    detail={`${summary.total} on the list`}
                                    href={guestsIndex.url(event.id)}
                                />
                                <Shortcut
                                    icon={ListChecks}
                                    title="RSVPs"
                                    detail={`${sent} sent`}
                                    href={rsvpsIndex.url(event.id)}
                                    locked={!hasFeature('rsvp_list')}
                                />
                                <Shortcut
                                    icon={MessageCircle}
                                    title="Messages"
                                    detail={
                                        messageIssues.failed > 0
                                            ? `${messageIssues.failed} failed`
                                            : 'Delivery log'
                                    }
                                    href={notificationsIndex.url(event.id)}
                                    locked={!hasFeature('message_log')}
                                />
                                <Shortcut
                                    icon={Sofa}
                                    title="Seating"
                                    detail={`${seated} of ${summary.headcount} seated`}
                                    href={seating.url(event.id)}
                                    locked={!hasFeature('seating')}
                                />
                                <Shortcut
                                    icon={Palette}
                                    title="Design"
                                    detail={
                                        event.template?.name ?? 'Invitation'
                                    }
                                    href={design.url(event.id)}
                                />
                                <Shortcut
                                    icon={ClipboardList}
                                    title="RSVP form"
                                    detail="What guests are asked"
                                    href={settings.url(event.id)}
                                />
                            </div>
                        </section>

                        {plan?.plan === 'starter' && (
                            <div className="relative isolate overflow-hidden rounded-2xl bg-hero p-4 text-sm text-hero-foreground ring-1 ring-hero-edge">
                                <div
                                    aria-hidden
                                    className="absolute -top-10 -right-10 -z-10 size-32 rounded-full bg-hero-glow/40 blur-2xl"
                                />
                                <p className="font-bold">
                                    You’re on the free Starter plan
                                </p>
                                <p className="mt-1 opacity-75">
                                    Up to {plan.max_guests_per_event} guests and
                                    1 invitation + 1 reminder per guest.
                                    Celebration adds public registration,
                                    seating, RSVP tracking and the message log.
                                </p>
                                <Link
                                    href={membership.url()}
                                    className="mt-3 inline-flex h-9 items-center gap-1.5 rounded-full bg-hero-accent px-4 text-xs font-bold text-hero"
                                >
                                    <Sparkles className="size-3.5" /> View
                                    membership
                                </Link>
                            </div>
                        )}
                    </aside>
                </div>
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

function Tile({
    icon: Icon,
    title,
    detail,
    href,
    tone,
    locked = false,
    color = 'bg-foreground/6 text-foreground',
}: {
    icon: LucideIcon;
    title: string;
    detail: string;
    href: string;
    tone?: 'warning' | 'danger';
    /** The icon tile's colours. */
    color?: string;
    /** Not in the client's plan: the page behind it shows an upgrade prompt. */
    locked?: boolean;
}) {
    return (
        <Link
            href={href}
            className="flex flex-col gap-3 rounded-2xl bg-card p-3.5 shadow-card active:scale-[0.98]"
        >
            <div className="flex items-start justify-between">
                <span
                    className={cn(
                        'flex size-10 items-center justify-center rounded-xl',
                        locked ? 'bg-foreground/6 text-subtle' : color,
                    )}
                >
                    <Icon className="size-5" strokeWidth={1.9} />
                </span>
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

/** The top of the phone overview: the invitation fills the screen's top edge with the event over it. */
function PhonePoster({ event }: { event: Event }) {
    const thumbnail = event.template?.thumbnail_url;

    return (
        <section className="relative isolate -mx-3 -mt-4 flex min-h-[26rem] flex-col overflow-hidden bg-hero px-4 pt-5 pb-18 text-white">
            {thumbnail ? (
                <img
                    src={thumbnail}
                    alt=""
                    aria-hidden
                    className="absolute inset-0 -z-20 size-full object-cover object-top"
                />
            ) : (
                <div
                    aria-hidden
                    className="absolute -top-20 -right-20 -z-20 size-80 rounded-full bg-hero-glow/40 blur-3xl"
                />
            )}
            <div
                aria-hidden
                className="absolute inset-0 -z-10 bg-linear-to-b from-black/50 via-black/20 to-black/85"
            />

            <div className="flex items-center justify-between gap-2">
                <EventSwitcher
                    event={event}
                    className="w-fit max-w-[70%] bg-white/15 text-white backdrop-blur-md"
                />
                <Link
                    href={design.url(event.id)}
                    aria-label="Edit invitation"
                    className="flex size-10 items-center justify-center rounded-full bg-white/15 backdrop-blur-md"
                >
                    <Palette className="size-4.5" />
                </Link>
            </div>

            <div className="mt-auto">
                <EventPills event={event} />
                <h1 className="mt-3 text-[30px] leading-[1.05] font-bold tracking-tight text-balance">
                    {event.title}
                </h1>
                <div className="mt-2.5 flex flex-col gap-1 text-sm text-white/85">
                    {event.event_date && (
                        <span className="inline-flex items-center gap-1.5">
                            <CalendarDays className="size-4" />
                            {formatShortDate(event.event_date, true)}
                            {event.start_time &&
                                ` · ${formatTime(event.start_time)}`}
                        </span>
                    )}
                    {event.location_name && (
                        <span className="inline-flex min-w-0 items-center gap-1.5">
                            <MapPin className="size-4 shrink-0" />
                            <span className="truncate">
                                {event.location_name}
                            </span>
                        </span>
                    )}
                </div>
            </div>
        </section>
    );
}

/** Replied share as a ring: attending, then declined, on the empty track. */
function RepliesRing({
    attending,
    declined,
    total,
}: {
    attending: number;
    declined: number;
    total: number;
}) {
    const radius = 30;
    const length = 2 * Math.PI * radius;
    const share = (part: number) => (total > 0 ? part / total : 0);

    return (
        <div className="relative size-20 shrink-0">
            <svg viewBox="0 0 72 72" className="size-full -rotate-90">
                <circle
                    cx="36"
                    cy="36"
                    r={radius}
                    fill="none"
                    strokeWidth="7"
                    className="stroke-foreground/8"
                />
                <circle
                    cx="36"
                    cy="36"
                    r={radius}
                    fill="none"
                    strokeWidth="7"
                    strokeLinecap="round"
                    className="stroke-success"
                    strokeDasharray={`${share(attending) * length} ${length}`}
                />
                <circle
                    cx="36"
                    cy="36"
                    r={radius}
                    fill="none"
                    strokeWidth="7"
                    strokeLinecap="round"
                    className="stroke-destructive"
                    strokeDasharray={`${share(declined) * length} ${length}`}
                    strokeDashoffset={-share(attending) * length}
                />
            </svg>
            <span className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-lg leading-none font-bold tabular-nums">
                    {Math.round(share(attending + declined) * 100)}%
                </span>
                <span className="text-[9px] text-muted-foreground">
                    replied
                </span>
            </span>
        </div>
    );
}

function WaitingRow({ guest, event }: { guest: Guest; event: Event }) {
    const state = invitationState(guest);
    const reminders = remindersUsed(guest, event.message_limits);

    return (
        <li className="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-raised">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-foreground/6 text-xs font-bold">
                {initials(guest.name)}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-semibold">
                    {guest.name}
                </span>
                <span className="block truncate text-xs text-muted-foreground">
                    {guest.phone ?? guest.email}
                </span>
            </span>
            <span
                className={cn(
                    'inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold',
                    state.failed
                        ? 'bg-destructive-muted text-destructive'
                        : 'bg-foreground/6 text-muted-foreground',
                )}
            >
                {state.failed && <AlertTriangle className="size-3" />}
                {state.label}
            </span>
            <span
                className={cn(
                    'hidden w-28 shrink-0 text-right text-xs xl:block',
                    reminders.atLimit ? 'text-subtle' : 'text-muted-foreground',
                )}
            >
                {reminders.label}
            </span>
        </li>
    );
}

/** The event's public link as a slim bar (under the event poster). */
function PublicLinkBar({ event }: { event: Event }) {
    const [copied, setCopied] = useState(false);

    if (!event.public_url) {
        return null;
    }

    const url = event.public_url;

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl bg-card px-5 py-3 shadow-card">
            <span className="flex size-8 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                <Link2 className="size-4" />
            </span>
            <span className="text-sm font-semibold">Public link</span>
            <span className="min-w-0 flex-1 truncate font-mono text-[13px] text-muted-foreground">
                {url.replace(/^https?:\/\//, '')}
            </span>
            <span className="text-xs text-subtle">
                Opened {event.public_link_open_count ?? 0} times
            </span>
            <button
                type="button"
                onClick={() =>
                    void navigator.clipboard
                        .writeText(url)
                        .then(() => setCopied(true))
                }
                className={cn(buttonVariants({ size: 'sm' }), 'rounded-full')}
            >
                {copied ? <CircleCheck /> : <Copy />}
                {copied ? 'Copied' : 'Copy'}
            </button>
            <a
                href={url}
                target="_blank"
                rel="noreferrer"
                aria-label="Open the public page"
                title="Open the public page"
                className="inline-flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-raised hover:text-foreground"
            >
                <ExternalLink className="size-4" />
            </a>
        </div>
    );
}

/** Invitations from not sent, to sent, to answered, with the reply split. */
function Pipeline({
    rsvpSummary,
    summary,
}: {
    rsvpSummary: RsvpSummary;
    summary: GuestSummary;
}) {
    const replied =
        rsvpSummary.accepted + rsvpSummary.declined + rsvpSummary.maybe;
    const sent = rsvpSummary.sent + replied;
    const invited = summary.total;
    const steps = [
        {
            label: 'On the list',
            value: invited,
            icon: Users,
            share: 1,
        },
        {
            label: 'Invited',
            value: sent,
            icon: Send,
            share: invited > 0 ? sent / invited : 0,
        },
        {
            label: 'Replied',
            value: replied,
            icon: MessageCircle,
            share: invited > 0 ? replied / invited : 0,
        },
    ];

    return (
        <section className="rounded-3xl bg-card p-5 shadow-card">
            <div className="flex items-end justify-between gap-4">
                <div>
                    <h2 className="text-[15px] font-bold">Responses</h2>
                    <p className="text-xs text-muted-foreground">
                        From the guest list to a reply
                    </p>
                </div>
                <span className="text-sm font-bold">
                    {summary.rsvp_confirmed + summary.rsvp_declined} of{' '}
                    {summary.total} parties replied
                </span>
            </div>

            <div className="mt-5 grid grid-cols-3 gap-3">
                {steps.map((step, index) => (
                    <div key={step.label} className="relative">
                        <div className="flex items-center gap-2 text-xs font-semibold text-muted-foreground">
                            <step.icon className="size-3.5" />
                            {step.label}
                            {index > 0 && (
                                <span className="ml-auto text-subtle">
                                    {Math.round(step.share * 100)}%
                                </span>
                            )}
                        </div>
                        <div className="mt-1.5 text-2xl font-bold tabular-nums">
                            {step.value}
                        </div>
                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-foreground/6">
                            <div
                                className="h-full rounded-full bg-primary transition-[width] duration-500"
                                style={{
                                    width: `${Math.min(1, step.share) * 100}%`,
                                    opacity: 0.45 + index * 0.27,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-6 rounded-2xl bg-raised p-4">
                <ResponseBar
                    attending={summary.rsvp_confirmed}
                    declined={summary.rsvp_declined}
                    total={summary.total}
                    legend={{ waiting: summary.rsvp_pending }}
                />
            </div>
        </section>
    );
}

/** A tile of the "Jump to" grid. */
function Shortcut({
    icon: Icon,
    title,
    detail,
    href,
    locked = false,
}: {
    icon: LucideIcon;
    title: string;
    detail: string;
    href: string;
    locked?: boolean;
}) {
    return (
        <Link
            href={href}
            className="group flex items-center gap-2.5 rounded-xl p-2.5 transition-colors hover:bg-raised"
        >
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-xl transition-colors',
                    locked
                        ? 'bg-foreground/6 text-subtle'
                        : 'bg-accent text-accent-foreground group-hover:bg-strong group-hover:text-strong-foreground',
                )}
            >
                <Icon className="size-4.5" strokeWidth={1.9} />
            </span>
            <span className="min-w-0">
                <span className="flex items-center gap-1 text-sm font-semibold">
                    {title}
                    {locked && (
                        <Lock
                            aria-label="Upgrade to unlock"
                            className="size-3 text-subtle"
                        />
                    )}
                </span>
                <span className="block truncate text-[11px] text-muted-foreground">
                    {locked ? 'Upgrade to unlock' : detail}
                </span>
            </span>
        </Link>
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
