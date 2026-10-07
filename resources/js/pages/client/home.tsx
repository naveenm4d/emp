import { Head, Link, router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    ArrowRight,
    CalendarDays,
    CircleCheck,
    Hourglass,
    PartyPopper,
    PencilLine,
    ChevronRight,
    Clock,
    Globe,
    LayoutTemplate,
    MapPin,
    Palette,
    Plus,
    Search,
    Send,
    Sofa,
    UserCheck,
    UserPlus,
} from 'lucide-react';

import {
    toApproveGuestsUrl,
    waitingGuestsUrl,
} from '@/components/events/event-card';
import { AttentionRow } from '@/components/shared/attention-row';
import { openCommandPalette } from '@/components/shared/command-palette';
import { DateTile } from '@/components/shared/date-tile';
import { EmptyState } from '@/components/shared/empty-state';
import { ResponseBar } from '@/components/shared/response-bar';
import { StatusBadge } from '@/components/shared/status-badge';
import { buttonVariants } from '@/components/ui/button';
import ClientLayout from '@/layouts/client-layout';
import {
    APP_TIME_ZONE,
    daysUntil,
    formatShortDate,
    formatTime,
    greeting,
    inDays,
    initials,
} from '@/lib/format';
import { useClientCan } from '@/lib/permissions';
import { useClientPlan, useHasPlanFeature } from '@/lib/plans';
import { cn } from '@/lib/utils';
import { create, design, index, seating, show } from '@/routes/client/events';
import { index as guestsIndex } from '@/routes/client/events/guests';
import { index as rsvpsIndex } from '@/routes/client/events/rsvps';
import { edit as profileEdit } from '@/routes/client/profile';
import { index as templatesIndex } from '@/routes/client/templates';
import type { Event, EventTotals, Paginated, Resource } from '@/types';

type Props = {
    events: Paginated<Event>;
    /** The soonest published upcoming event (never a draft or cancelled). */
    next: Resource<Event> | null;
    totals: EventTotals;
};

/** "Sunday, 27 September" in Sri Lanka time. */
function today(): string {
    return new Intl.DateTimeFormat('en-GB', {
        timeZone: APP_TIME_ZONE,
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(new Date());
}

/** "2 upcoming · 14 parties waiting on a reply". */
function summaryLine(totals: EventTotals): string {
    if (totals.upcoming === 0) {
        return 'Nothing on the calendar yet.';
    }

    const upcoming = `${totals.upcoming} upcoming ${totals.upcoming === 1 ? 'event' : 'events'}`;

    return totals.waiting > 0
        ? `${upcoming} · ${totals.waiting} ${totals.waiting === 1 ? 'party' : 'parties'} waiting on a reply`
        : `${upcoming} · every reply is in`;
}

export default function Home({ events, next: nextEvent, totals }: Props) {
    const { auth } = usePage().props;
    const plan = useClientPlan();
    const can = useClientCan();
    const next = nextEvent?.data ?? null;
    const others = events.data.filter((event) => event.id !== next?.id);
    const firstName = (auth.user?.name ?? '').split(' ')[0];
    const canCreateEvents = can('events.create');
    const canCreate = canCreateEvents && (!plan || plan.can_create_event);

    return (
        <ClientLayout>
            <Head title="Home" />

            <PhoneHome events={events.data} next={next} others={others} />

            <div className="mx-auto hidden max-w-6xl flex-col gap-6 md:flex">
                <Banner
                    firstName={firstName}
                    totals={totals}
                    canCreate={canCreate}
                    reason={plan?.reason ?? null}
                />

                <div className="grid grid-cols-1 items-start gap-6 *:min-w-0 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    {next ? (
                        <Feature event={next} />
                    ) : (
                        <FirstEvent canCreate={canCreate} />
                    )}

                    <aside className="flex flex-col gap-6">
                        {hasAttention(events.data) ? (
                            <NeedsAttention events={events.data} />
                        ) : (
                            <CaughtUp />
                        )}
                        {others.length > 0 && <Timeline events={others} />}
                    </aside>
                </div>
            </div>
        </ClientLayout>
    );
}

/** The top of the page: greeting, where things stand, and the main actions. */
function Banner({
    firstName,
    totals,
    canCreate,
    reason,
}: {
    firstName: string;
    totals: EventTotals;
    canCreate: boolean;
    reason: string | null;
}) {
    const stats: {
        label: string;
        value: number;
        icon: LucideIcon;
        href: string;
    }[] = [
        {
            label: 'Upcoming',
            value: totals.upcoming,
            icon: CalendarDays,
            href: index.url({ query: { period: 'upcoming' } }),
        },
        {
            label: 'Drafts',
            value: totals.drafts,
            icon: PencilLine,
            href: index.url({ query: { state: 'draft' } }),
        },
        {
            label: 'Awaiting reply',
            value: totals.waiting,
            icon: Hourglass,
            href: index.url({ query: { period: 'upcoming' } }),
        },
    ];

    return (
        <section className="relative isolate overflow-hidden rounded-3xl bg-hero px-8 py-9 text-hero-foreground shadow-card ring-1 ring-hero-edge lg:px-10">
            <div
                aria-hidden
                className="absolute -top-32 -right-20 -z-10 size-[26rem] rounded-full bg-hero-glow/40 blur-3xl"
            />
            <div
                aria-hidden
                className="absolute -bottom-40 left-1/3 -z-10 size-[24rem] rounded-full bg-hero-glow-soft/25 blur-3xl"
            />
            <div
                aria-hidden
                className="absolute inset-0 -z-10 bg-[radial-gradient(currentColor_1px,transparent_1px)] bg-size-[22px_22px] opacity-[0.06]"
            />

            <div className="flex flex-wrap items-end justify-between gap-8">
                <div className="max-w-xl">
                    <p className="text-[11px] font-bold tracking-[0.2em] uppercase opacity-70">
                        {today()}
                    </p>
                    <h1 className="mt-3 text-4xl leading-[1.05] font-bold tracking-tight lg:text-5xl">
                        {greeting()},
                        <br />
                        <span className="text-hero-accent">{firstName}</span>
                    </h1>
                    <p className="mt-3 text-sm opacity-75">
                        {summaryLine(totals)}
                    </p>
                    <div className="mt-6 flex flex-wrap gap-2.5">
                        {canCreate ? (
                            <Link
                                href={create.url()}
                                className="inline-flex h-11 items-center gap-2 rounded-full bg-hero-accent px-5 text-sm font-bold text-hero shadow-[0_8px_24px_-8px_var(--hero-glow)] transition-transform hover:-translate-y-0.5"
                            >
                                <Plus className="size-4" /> New event
                            </Link>
                        ) : (
                            <span
                                title={reason ?? undefined}
                                className="inline-flex h-11 cursor-not-allowed items-center gap-2 rounded-full bg-current/10 px-5 text-sm font-bold opacity-60"
                            >
                                <Plus className="size-4" /> New event
                            </span>
                        )}
                        <Link
                            href={templatesIndex.url()}
                            className="inline-flex h-11 items-center gap-2 rounded-full bg-current/10 px-5 text-sm font-semibold transition-colors hover:bg-current/15"
                        >
                            <LayoutTemplate className="size-4" /> Browse
                            templates
                        </Link>
                        <button
                            type="button"
                            aria-label="Search"
                            title="Search (⌘K)"
                            onClick={openCommandPalette}
                            className="inline-flex size-11 items-center justify-center rounded-full bg-current/10 transition-colors hover:bg-current/15"
                        >
                            <Search className="size-4" />
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-3">
                    {stats.map((stat) => (
                        <Link
                            key={stat.label}
                            href={stat.href}
                            className="flex w-28 flex-col gap-3 rounded-2xl bg-current/[0.07] p-4 ring-1 ring-current/10 backdrop-blur transition-colors hover:bg-current/[0.12] lg:w-32"
                        >
                            <stat.icon className="size-4.5 opacity-70" />
                            <span className="text-3xl leading-none font-bold tabular-nums">
                                {stat.value}
                            </span>
                            <span className="text-[11px] font-semibold opacity-70">
                                {stat.label}
                            </span>
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}

/** The next event, big: its invitation, when and where, the countdown, replies and the shortcuts. */
function Feature({ event }: { event: Event }) {
    const hasFeature = useHasPlanFeature();
    const days = event.event_date ? daysUntil(event.event_date) : null;
    const thumbnail = event.template?.thumbnail_url;

    const actions: { icon: LucideIcon; label: string; href: string }[] = [
        { icon: UserPlus, label: 'Add guest', href: guestsIndex.url(event.id) },
        {
            icon: Send,
            label: 'Invite',
            href: hasFeature('rsvp_list')
                ? rsvpsIndex.url(event.id)
                : guestsIndex.url(event.id),
        },
        ...(hasFeature('seating')
            ? [{ icon: Sofa, label: 'Seating', href: seating.url(event.id) }]
            : []),
        { icon: Palette, label: 'Design', href: design.url(event.id) },
    ];

    return (
        <section className="overflow-hidden rounded-3xl bg-card shadow-card">
            <div className="grid gap-0 xl:grid-cols-[15rem_minmax(0,1fr)]">
                <Link
                    href={design.url(event.id)}
                    className="group relative block min-h-56 overflow-hidden bg-raised"
                    aria-label={`Design the invitation for ${event.title}`}
                >
                    {thumbnail ? (
                        <img
                            src={thumbnail}
                            alt=""
                            className="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                    ) : (
                        <span className="absolute inset-0 flex items-center justify-center text-subtle">
                            <Palette className="size-10" strokeWidth={1.25} />
                        </span>
                    )}
                    <span className="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/70 to-transparent p-4 pt-12 text-white">
                        <span className="block text-[10px] font-bold tracking-[0.16em] uppercase opacity-80">
                            Invitation
                        </span>
                        <span className="block truncate text-sm font-semibold">
                            {event.template?.name ?? 'Choose a design'}
                        </span>
                    </span>
                </Link>

                <div className="flex min-w-0 flex-col gap-6 p-6 lg:p-7">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <span className="inline-flex items-center gap-2 text-[11px] font-bold tracking-[0.16em] text-link uppercase">
                                <span className="relative flex size-2">
                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-primary opacity-60" />
                                    <span className="relative inline-flex size-2 rounded-full bg-primary" />
                                </span>
                                Next up
                            </span>
                            <Link
                                href={show.url(event.id)}
                                className="mt-2 block text-2xl leading-tight font-bold tracking-tight hover:underline lg:text-3xl"
                            >
                                {event.title}
                            </Link>
                            <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-muted-foreground">
                                {event.event_date && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <CalendarDays className="size-4" />
                                        {formatShortDate(
                                            event.event_date,
                                            true,
                                        )}
                                    </span>
                                )}
                                {event.start_time && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <Clock className="size-4" />
                                        {formatTime(event.start_time)}
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
                        <Countdown days={days} />
                    </div>

                    <Replies event={event} />

                    <div className="flex flex-wrap gap-2">
                        {actions.map(({ icon: Icon, label, href }) => (
                            <Link
                                key={label}
                                href={href}
                                className="inline-flex h-10 items-center gap-2 rounded-full border border-input px-4 text-sm font-semibold transition-colors hover:border-transparent hover:bg-strong hover:text-strong-foreground"
                            >
                                <Icon className="size-4" strokeWidth={2} />
                                {label}
                            </Link>
                        ))}
                        <Link
                            href={show.url(event.id)}
                            className="ml-auto inline-flex h-10 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-link hover:underline"
                        >
                            Open event <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}

/** "48 days to go" as a tile (or Today / Tomorrow / Date TBC). */
function Countdown({ days }: { days: number | null }) {
    return (
        <div className="flex size-24 shrink-0 flex-col items-center justify-center rounded-2xl bg-hero text-hero-foreground ring-1 ring-hero-edge">
            {days !== null && days > 1 ? (
                <>
                    <span className="text-4xl leading-none font-bold tracking-tighter tabular-nums">
                        {days}
                    </span>
                    <span className="mt-1 text-[11px] font-semibold opacity-70">
                        days to go
                    </span>
                </>
            ) : (
                <span className="px-2 text-center text-lg leading-tight font-bold">
                    {days === null
                        ? 'Date TBC'
                        : days === 0
                          ? 'Today'
                          : 'Tomorrow'}
                </span>
            )}
        </div>
    );
}

/** A ring of who replied (attending, declined) against everyone invited, with the counts. */
function Replies({ event }: { event: Event }) {
    const guests = event.guests_count ?? 0;
    const attending = event.attending_count ?? 0;
    const declined = event.declined_count ?? 0;
    const waiting = event.waiting_count ?? 0;
    const radius = 34;
    const length = 2 * Math.PI * radius;
    const share = (part: number) => (guests > 0 ? part / guests : 0);

    return (
        <div className="flex items-center gap-6 rounded-2xl bg-raised p-4">
            <div className="relative size-24 shrink-0">
                <svg viewBox="0 0 80 80" className="size-full -rotate-90">
                    <circle
                        cx="40"
                        cy="40"
                        r={radius}
                        fill="none"
                        strokeWidth="8"
                        className="stroke-foreground/8"
                    />
                    <circle
                        cx="40"
                        cy="40"
                        r={radius}
                        fill="none"
                        strokeWidth="8"
                        strokeLinecap="round"
                        className="stroke-success"
                        strokeDasharray={`${share(attending) * length} ${length}`}
                    />
                    <circle
                        cx="40"
                        cy="40"
                        r={radius}
                        fill="none"
                        strokeWidth="8"
                        strokeLinecap="round"
                        className="stroke-destructive"
                        strokeDasharray={`${share(declined) * length} ${length}`}
                        strokeDashoffset={-share(attending) * length}
                    />
                </svg>
                <span className="absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-xl leading-none font-bold tabular-nums">
                        {Math.round(share(attending + declined) * 100)}%
                    </span>
                    <span className="text-[10px] text-muted-foreground">
                        replied
                    </span>
                </span>
            </div>
            <div className="grid flex-1 grid-cols-3 gap-3">
                <ReplyCount
                    value={attending}
                    label="Attending"
                    dot="bg-success"
                />
                <ReplyCount
                    value={declined}
                    label="Declined"
                    dot="bg-destructive"
                />
                <ReplyCount
                    value={waiting}
                    label="Waiting"
                    dot="bg-foreground/25"
                />
                <p className="col-span-3 text-xs text-muted-foreground">
                    {attending + declined} of {guests}{' '}
                    {guests === 1 ? 'party has' : 'parties have'} replied
                </p>
            </div>
        </div>
    );
}

function ReplyCount({
    value,
    label,
    dot,
}: {
    value: number;
    label: string;
    dot: string;
}) {
    return (
        <div>
            <div className="text-2xl leading-none font-bold tabular-nums">
                {value}
            </div>
            <div className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                <span className={cn('size-2 rounded-full', dot)} />
                {label}
            </div>
        </div>
    );
}

/** No published upcoming event: invite the client to plan one. */
function FirstEvent({ canCreate }: { canCreate: boolean }) {
    return (
        <section className="relative isolate overflow-hidden rounded-3xl bg-card p-8 shadow-card">
            <div
                aria-hidden
                className="absolute -top-24 -right-16 -z-10 size-72 rounded-full bg-primary/15 blur-3xl"
            />
            <span className="flex size-12 items-center justify-center rounded-2xl bg-primary/15 text-primary">
                <PartyPopper className="size-6" />
            </span>
            <h2 className="mt-5 max-w-md text-2xl leading-tight font-bold tracking-tight">
                Nothing live yet. Plan something worth celebrating.
            </h2>
            <p className="mt-2 max-w-md text-sm text-muted-foreground">
                Create an event, design the invitation and send RSVP links on
                WhatsApp. Publish it and it shows up here.
            </p>
            <div className="mt-6 flex flex-wrap gap-2.5">
                {canCreate && (
                    <Link
                        href={create.url()}
                        className="inline-flex h-11 items-center gap-2 rounded-full bg-primary px-5 text-sm font-bold text-primary-foreground"
                    >
                        <Plus className="size-4" /> New event
                    </Link>
                )}
                <Link
                    href={templatesIndex.url()}
                    className="inline-flex h-11 items-center gap-2 rounded-full border border-input px-5 text-sm font-semibold"
                >
                    <LayoutTemplate className="size-4" /> Browse templates
                </Link>
            </div>
        </section>
    );
}

function hasAttention(events: Event[]): boolean {
    return events.some(
        (event) =>
            (event.to_approve_count ?? 0) > 0 ||
            (event.failed_messages_count ?? 0) > 0,
    );
}

/** Shown in place of "Needs attention" when nothing does. */
function CaughtUp() {
    return (
        <section className="flex items-center gap-3.5 rounded-2xl bg-card p-4 shadow-card">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-success-muted text-success">
                <CircleCheck className="size-5.5" />
            </span>
            <div>
                <p className="text-sm font-bold">All caught up</p>
                <p className="text-xs text-muted-foreground">
                    No approvals waiting and no failed messages.
                </p>
            </div>
        </section>
    );
}

/** Registrations to approve and failed invitations, one row per event. */
function NeedsAttention({ events }: { events: Event[] }) {
    const rows = events.flatMap((event) => [
        ...((event.to_approve_count ?? 0) > 0
            ? [
                  <AttentionRow
                      key={`${event.id}-approve`}
                      icon={UserCheck}
                      tone="warning"
                      title={`${event.to_approve_count} ${event.to_approve_count === 1 ? 'registration' : 'registrations'} to approve`}
                      description={event.title}
                      action="Review"
                      href={toApproveGuestsUrl(event)}
                  />,
              ]
            : []),
        ...((event.failed_messages_count ?? 0) > 0
            ? [
                  <AttentionRow
                      key={`${event.id}-failed`}
                      icon={AlertTriangle}
                      tone="danger"
                      title={`${event.failed_messages_count} ${event.failed_messages_count === 1 ? 'invitation' : 'invitations'} failed`}
                      description={event.title}
                      action="Fix"
                      href={waitingGuestsUrl(event)}
                  />,
              ]
            : []),
    ]);

    if (rows.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-2 md:gap-2.5">
            <h2 className="px-1 text-sm font-bold">Needs attention</h2>
            <div className="overflow-hidden rounded-xl bg-card shadow-card md:rounded-2xl">
                {rows}
            </div>
        </section>
    );
}

/** The other upcoming events as a timeline down the side. */
function Timeline({ events }: { events: Event[] }) {
    return (
        <section className="flex flex-col gap-2.5">
            <div className="flex items-center justify-between px-1">
                <h2 className="text-sm font-bold">Coming up</h2>
                <Link
                    href={index.url()}
                    className="inline-flex items-center gap-1 text-xs font-semibold text-link"
                >
                    See all <ArrowRight className="size-3.5" />
                </Link>
            </div>
            <ol className="relative flex flex-col gap-2.5 before:absolute before:top-6 before:bottom-6 before:left-[33px] before:w-px before:bg-border">
                {events.map((event) => {
                    const guests = event.guests_count ?? 0;
                    const cancelled = event.state === 'cancelled';

                    return (
                        <li key={event.id} className="relative">
                            <Link
                                href={show.url(event.id)}
                                className={cn(
                                    'flex items-center gap-3 rounded-2xl bg-card p-3 shadow-card transition-transform hover:-translate-y-0.5',
                                    cancelled && 'opacity-60',
                                )}
                            >
                                <DateTile
                                    date={event.event_date}
                                    size="sm"
                                    className="bg-card"
                                />
                                <span className="min-w-0 flex-1">
                                    <span
                                        className={cn(
                                            'block truncate text-sm font-semibold',
                                            cancelled && 'line-through',
                                        )}
                                    >
                                        {event.title}
                                    </span>
                                    <span className="mt-0.5 block text-xs text-muted-foreground">
                                        {event.event_date
                                            ? inDays(event.event_date)
                                            : 'Date TBC'}{' '}
                                        · {guests}{' '}
                                        {guests === 1 ? 'guest' : 'guests'}
                                    </span>
                                    <ResponseBar
                                        size="sm"
                                        className="mt-1.5"
                                        attending={event.attending_count ?? 0}
                                        declined={event.declined_count ?? 0}
                                        total={guests}
                                    />
                                </span>
                                {event.state !== 'published' && (
                                    <StatusBadge status={event.state} />
                                )}
                            </Link>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

/** "Next up · in 48 days" / "Next up · today". */
function nextUpLabel(event: Event): string {
    if (!event.event_date) {
        return 'Next up';
    }

    const days = daysUntil(event.event_date);

    return `Next up · ${days === 0 ? 'today' : days === 1 ? 'tomorrow' : `in ${days} days`}`;
}

/** "Sat · 6:30 PM · Cinnamon Grand, Colombo". */
function whenAndWhere(event: Event): string {
    return [
        event.event_date
            ? formatShortDate(event.event_date).split(' ')[0]
            : null,
        formatTime(event.start_time) || null,
        event.location_name,
    ]
        .filter(Boolean)
        .join(' · ');
}

/** Phones: the app header band, the next-up card, quick actions and the other events. */
function PhoneHome({
    events,
    next,
    others,
}: {
    events: Event[];
    next: Event | null;
    others: Event[];
}) {
    const { auth } = usePage().props;
    const hasFeature = useHasPlanFeature();
    const canCreateEvents = useClientCan()('events.create');
    const firstName = (auth.user?.name ?? '').split(' ')[0];

    return (
        <div className="md:hidden">
            <div className="-mx-3 -mt-4 flex flex-col gap-4 bg-sidebar px-4 pt-5 pb-16 text-sidebar-foreground">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="flex size-7 items-center justify-center rounded-md bg-primary text-[13px] font-extrabold text-primary-foreground">
                            E
                        </div>
                        <span className="text-base font-bold tracking-[0.02em]">
                            EMP
                        </span>
                    </div>
                    <Link
                        href={profileEdit.url()}
                        aria-label="Account"
                        className="flex size-9 items-center justify-center rounded-full bg-card text-[13px] font-bold text-card-foreground"
                    >
                        {initials(auth.user?.name ?? '')}
                    </Link>
                </div>
                <div>
                    <div className="text-sm text-sidebar-foreground/75">
                        {greeting()}, {firstName}
                    </div>
                    <h1 className="mt-0.5 text-2xl font-bold">Your events</h1>
                </div>
                <form
                    className="relative"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const search = new FormData(e.currentTarget).get(
                            'search',
                        );
                        router.get(index.url(), { search: search || null });
                    }}
                >
                    <Search className="pointer-events-none absolute top-1/2 left-4 size-4.5 -translate-y-1/2 text-subtle" />
                    <input
                        name="search"
                        type="search"
                        placeholder="Search events"
                        className="h-11 w-full rounded-full bg-card pr-4 pl-11 text-sm text-card-foreground outline-none placeholder:text-subtle focus-visible:ring-3 focus-visible:ring-ring/50"
                    />
                </form>
            </div>

            <div className="flex flex-col gap-4">
                {next ? (
                    <NextUpCard event={next} />
                ) : (
                    <div className="relative -mt-11 rounded-2xl bg-card shadow-card">
                        <EmptyState
                            icon={CalendarDays}
                            title="No upcoming events"
                            description="Create an event to start inviting guests."
                            action={
                                canCreateEvents && (
                                    <Link
                                        href={create.url()}
                                        className={buttonVariants({
                                            size: 'sm',
                                        })}
                                    >
                                        New event
                                    </Link>
                                )
                            }
                        />
                    </div>
                )}

                {next && (
                    <div className="grid grid-cols-4 gap-2 pt-1">
                        <QuickAction
                            icon={UserPlus}
                            label="Add guest"
                            href={guestsIndex.url(next.id)}
                        />
                        <QuickAction
                            icon={Send}
                            label="Invite"
                            href={
                                hasFeature('rsvp_list')
                                    ? rsvpsIndex.url(next.id)
                                    : guestsIndex.url(next.id)
                            }
                        />
                        {hasFeature('seating') && (
                            <QuickAction
                                icon={Sofa}
                                label="Seating"
                                href={seating.url(next.id)}
                            />
                        )}
                        <QuickAction
                            icon={Palette}
                            label="Design"
                            href={design.url(next.id)}
                        />
                    </div>
                )}

                <NeedsAttention events={events} />

                {others.length > 0 && (
                    <section className="flex flex-col gap-2">
                        <div className="flex items-center justify-between px-1">
                            <h2 className="text-sm font-bold">Other events</h2>
                            <Link
                                href={index.url()}
                                className="text-xs font-semibold text-link"
                            >
                                See all
                            </Link>
                        </div>
                        <div className="flex flex-col gap-2">
                            {others.map((event) => (
                                <OtherEvent key={event.id} event={event} />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </div>
    );
}

function NextUpCard({ event }: { event: Event }) {
    const guests = event.guests_count ?? 0;
    const attending = event.attending_count ?? 0;
    const declined = event.declined_count ?? 0;

    return (
        <div className="relative -mt-11 flex flex-col gap-3.5 rounded-2xl bg-card p-4 shadow-[0_6px_20px_rgba(0,0,0,0.14)]">
            <div className="flex items-center justify-between gap-2">
                <span className="text-[11px] font-bold tracking-[0.08em] text-link uppercase">
                    {nextUpLabel(event)}
                </span>
                {event.state === 'published' ? (
                    <span className="inline-flex items-center gap-1 rounded-full bg-success-muted px-2 py-0.75 text-[11px] font-semibold text-success">
                        <Globe className="size-3" /> Published
                    </span>
                ) : (
                    <StatusBadge status={event.state} />
                )}
            </div>
            <Link href={show.url(event.id)} className="flex items-center gap-3">
                <DateTile date={event.event_date} size="lg" />
                <div className="min-w-0 flex-1">
                    <div className="text-base font-bold">{event.title}</div>
                    <div className="mt-0.75 text-xs text-muted-foreground">
                        {whenAndWhere(event)}
                    </div>
                </div>
                <ChevronRight className="size-5 text-subtle" />
            </Link>
            <div className="flex flex-col gap-1.5">
                <div className="flex justify-between text-xs">
                    <span className="text-muted-foreground">Responses</span>
                    <span className="font-bold">
                        {attending + declined} of {guests} parties
                    </span>
                </div>
                <ResponseBar
                    attending={attending}
                    declined={declined}
                    total={guests}
                    legend={{ waiting: event.waiting_count ?? 0 }}
                />
            </div>
        </div>
    );
}

function QuickAction({
    icon: Icon,
    label,
    href,
}: {
    icon: LucideIcon;
    label: string;
    href: string;
}) {
    return (
        <Link
            href={href}
            className="flex flex-col items-center gap-1.5 text-[11px] font-semibold text-muted-foreground"
        >
            <span className="flex size-14 items-center justify-center rounded-xl bg-card text-foreground shadow-card">
                <Icon className="size-5.5" strokeWidth={1.75} />
            </span>
            {label}
        </Link>
    );
}

function OtherEvent({ event }: { event: Event }) {
    return (
        <Link
            href={show.url(event.id)}
            className="flex items-center gap-3 rounded-xl bg-card p-3 shadow-card"
        >
            <span className="flex size-10 items-center justify-center rounded-md bg-foreground/6 text-muted-foreground">
                <CalendarDays className="size-4.5" strokeWidth={1.75} />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-semibold">
                    {event.title}
                </span>
                <span className="block text-xs text-subtle">
                    {formatShortDate(event.event_date)} ·{' '}
                    {event.guests_count ?? 0} guests
                </span>
            </span>
            <span className={cn(event.state === 'published' && 'hidden')}>
                <StatusBadge status={event.state} />
            </span>
        </Link>
    );
}
