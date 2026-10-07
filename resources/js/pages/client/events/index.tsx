import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarDays,
    MoreHorizontal,
    Plus,
    Search,
    UserCheck,
} from 'lucide-react';

import {
    EventCard,
    toApproveGuestsUrl,
    waitingGuestsUrl,
} from '@/components/events/event-card';
import { PlanLimitNotice } from '@/components/plans/plan-limit-notice';
import { openCommandPalette } from '@/components/shared/command-palette';
import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { ResponseBar } from '@/components/shared/response-bar';
import { SectionLabel } from '@/components/shared/section-label';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { SegmentedControl } from '@/components/ui/segmented-control';
import ClientLayout from '@/layouts/client-layout';
import { formatShortDate, initials, todayInAppTimeZone } from '@/lib/format';
import { useClientCan } from '@/lib/permissions';
import { useClientPlan } from '@/lib/plans';
import { create, design, index, show } from '@/routes/client/events';
import { index as guestsIndex } from '@/routes/client/events/guests';
import { edit as profileEdit } from '@/routes/client/profile';
import type { Event, EventTotals, Option, Paginated } from '@/types';

type Filters = {
    state: string | null;
    search: string | null;
    period: string | null;
};

type Props = {
    events: Paginated<Event>;
    filters: Filters;
    states: Option[];
    totals: EventTotals;
};

type Segment = 'upcoming' | 'drafts' | 'past' | 'all';

const SEGMENT_FILTERS: Record<Segment, Partial<Filters>> = {
    upcoming: { period: 'upcoming', state: null },
    drafts: { period: null, state: 'draft' },
    past: { period: 'past', state: null },
    all: { period: null, state: null },
};

function currentSegment(filters: Filters): Segment {
    if (filters.state === 'draft' && !filters.period) {
        return 'drafts';
    }

    if (filters.period === 'upcoming' || filters.period === 'past') {
        return filters.period;
    }

    return 'all';
}

/** Group label for a card: "This month", "November 2026", "Not dated". */
function monthLabel(date: string | null): string {
    if (!date) {
        return 'Not dated';
    }

    if (date.slice(0, 7) === todayInAppTimeZone().slice(0, 7)) {
        return 'This month';
    }

    return new Date(`${date.slice(0, 10)}T00:00:00Z`).toLocaleDateString(
        'en-GB',
        { timeZone: 'UTC', month: 'long', year: 'numeric' },
    );
}

function groupByMonth(events: Event[]): [string, Event[]][] {
    const groups = new Map<string, Event[]>();

    for (const event of events) {
        const label = monthLabel(event.event_date);
        groups.set(label, [...(groups.get(label) ?? []), event]);
    }

    return [...groups];
}

export default function EventsIndex({ events, filters, totals }: Props) {
    const plan = useClientPlan();
    const can = useClientCan();
    const { auth } = usePage().props;
    const segment = currentSegment(filters);
    const filter = (changes: Partial<Filters>) =>
        router.get(
            index.url(),
            { ...filters, ...changes },
            { preserveState: true, replace: true },
        );

    const canCreateEvents = can('events.create');
    const newEvent = !canCreateEvents ? null : plan &&
      !plan.can_create_event ? (
        <Button size="lg" disabled title={plan.reason ?? undefined}>
            <Plus /> New event
        </Button>
    ) : (
        <Link href={create.url()} className={buttonVariants({ size: 'lg' })}>
            <Plus /> New event
        </Link>
    );

    return (
        <ClientLayout>
            <Head title="Events" />
            <PageHeader
                title="Events"
                description={
                    <span className="hidden md:inline">
                        {totals.upcoming} upcoming · {totals.waiting} parties
                        waiting on a reply
                    </span>
                }
                actions={
                    <>
                        <div className="hidden md:flex">{newEvent}</div>
                        <button
                            type="button"
                            aria-label="Search"
                            onClick={openCommandPalette}
                            className="flex size-10 items-center justify-center rounded-full bg-foreground/6 md:hidden"
                        >
                            <Search className="size-5" strokeWidth={1.75} />
                        </button>
                        <Link
                            href={profileEdit.url()}
                            aria-label="Account"
                            className="flex size-10 items-center justify-center rounded-full bg-strong text-[13px] font-bold text-strong-foreground md:hidden"
                        >
                            {initials(auth.user?.name ?? '')}
                        </Link>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-2">
                    <SegmentedControl
                        label="Events"
                        className="w-full md:w-80"
                        value={segment}
                        onChange={(value) =>
                            filter({ ...SEGMENT_FILTERS[value], search: null })
                        }
                        items={[
                            {
                                value: 'upcoming' as Segment,
                                label: 'Upcoming',
                                count: totals.upcoming,
                            },
                            {
                                value: 'drafts' as Segment,
                                label: 'Drafts',
                                count: totals.drafts,
                            },
                            { value: 'past' as Segment, label: 'Past' },
                        ]}
                    />
                    <label className="relative hidden md:block">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle" />
                        <Input
                            className="w-64 border-transparent bg-card pl-9 shadow-card"
                            placeholder="Search events"
                            defaultValue={filters.search ?? ''}
                            onKeyDown={(e) =>
                                e.key === 'Enter' &&
                                filter({
                                    ...SEGMENT_FILTERS.all,
                                    search: e.currentTarget.value || null,
                                })
                            }
                        />
                    </label>
                </div>
            </PageHeader>
            <PlanLimitNotice />

            {events.data.length === 0 ? (
                <div className="rounded-xl bg-card shadow-card">
                    <EmptyState
                        icon={CalendarDays}
                        title={
                            segment === 'past'
                                ? 'No past events'
                                : segment === 'drafts'
                                  ? 'No drafts'
                                  : 'No events yet'
                        }
                        description="Create an event to start inviting guests."
                        action={
                            canCreateEvents && (
                                <Link
                                    href={create.url()}
                                    className={buttonVariants({ size: 'sm' })}
                                >
                                    New event
                                </Link>
                            )
                        }
                    />
                </div>
            ) : (
                <>
                    <div className="flex flex-col gap-2.5 lg:hidden">
                        {groupByMonth(events.data).map(([label, group]) => (
                            <section
                                key={label}
                                className="flex flex-col gap-2.5"
                            >
                                <SectionLabel>{label}</SectionLabel>
                                {group.map((event) => (
                                    <EventCard
                                        key={event.id}
                                        event={event}
                                        highlight={
                                            segment === 'upcoming' &&
                                            event.id === events.data[0].id &&
                                            events.meta.current_page === 1
                                        }
                                    />
                                ))}
                            </section>
                        ))}
                    </div>

                    <EventsTable events={events.data} />
                </>
            )}
            <Pagination meta={events.meta} />
        </ClientLayout>
    );
}

/** Desktop list (4a): responses, parties waiting and what needs action. */
function EventsTable({ events }: { events: Event[] }) {
    return (
        <div className="hidden overflow-hidden rounded-lg bg-card shadow-card lg:block">
            <table className="w-full border-collapse text-[13px]">
                <thead>
                    <tr className="bg-raised text-left text-[11px] tracking-[0.06em] text-muted-foreground">
                        <th className="px-4 py-2.5 font-bold">EVENT</th>
                        <th className="px-3 py-2.5 font-bold">DATE</th>
                        <th className="w-60 px-3 py-2.5 font-bold">
                            RESPONSES
                        </th>
                        <th className="px-3 py-2.5 text-right font-bold">
                            WAITING
                        </th>
                        <th className="px-3 py-2.5 font-bold">NEEDS ACTION</th>
                        <th className="px-4 py-2.5">
                            <span className="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {events.map((event) => (
                        <EventRow key={event.id} event={event} />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function EventRow({ event }: { event: Event }) {
    const guests = event.guests_count ?? 0;
    const attending = event.attending_count ?? 0;
    const declined = event.declined_count ?? 0;
    const toApprove = event.to_approve_count ?? 0;
    const failed = event.failed_messages_count ?? 0;

    return (
        <tr
            className="cursor-pointer border-t border-border hover:bg-raised/60"
            onClick={() => router.visit(show.url(event.id))}
        >
            <td className="px-4 py-3">
                <Link
                    href={show.url(event.id)}
                    className="font-semibold"
                    onClick={(e) => e.stopPropagation()}
                >
                    {event.title}
                </Link>
                <div className="flex items-center gap-1.5 text-xs text-subtle">
                    {event.state !== 'published' && (
                        <StatusBadge status={event.state} />
                    )}
                    {event.registration_type_label}
                </div>
            </td>
            <td className="px-3 py-3 whitespace-nowrap">
                {formatShortDate(event.event_date)}
            </td>
            <td className="px-3 py-3">
                <ResponseBar
                    attending={attending}
                    declined={declined}
                    total={guests}
                />
                <div className="mt-1 text-[11px] text-muted-foreground">
                    {attending} yes · {declined} no · of {guests}
                </div>
            </td>
            <td className="px-3 py-3 text-right font-bold tabular-nums">
                {event.waiting_count ?? 0}
            </td>
            <td className="px-3 py-3">
                {failed > 0 ? (
                    <Link
                        href={waitingGuestsUrl(event)}
                        onClick={(e) => e.stopPropagation()}
                        className="inline-flex items-center gap-1 font-semibold text-destructive"
                    >
                        <AlertTriangle className="size-3.25" />
                        {failed} failed
                    </Link>
                ) : toApprove > 0 ? (
                    <Link
                        href={toApproveGuestsUrl(event)}
                        onClick={(e) => e.stopPropagation()}
                        className="inline-flex items-center gap-1 font-semibold text-warning"
                    >
                        <UserCheck className="size-3.25" />
                        {toApprove} to approve
                    </Link>
                ) : (
                    <span className="text-subtle">—</span>
                )}
            </td>
            <td
                className="px-4 py-3 text-right text-muted-foreground"
                onClick={(e) => e.stopPropagation()}
            >
                <DropdownMenu>
                    <DropdownMenuTrigger
                        aria-label={`Actions for ${event.title}`}
                        className="rounded-md p-1 hover:bg-raised hover:text-foreground"
                    >
                        <MoreHorizontal className="size-4" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent>
                        <DropdownMenuItem
                            render={<Link href={show.url(event.id)} />}
                        >
                            Overview
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            render={<Link href={guestsIndex.url(event.id)} />}
                        >
                            Guests
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            render={<Link href={design.url(event.id)} />}
                        >
                            Invitation design
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </td>
        </tr>
    );
}
