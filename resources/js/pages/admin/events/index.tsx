import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CalendarDays,
    Eye,
    Lock,
    MoreHorizontal,
    Pencil,
    Trash2,
    UserRound,
} from 'lucide-react';

import { ConfirmBar, useConfirm } from '@/components/shared/confirm-bar';
import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { StatusBadge } from '@/components/shared/status-badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SegmentedControl } from '@/components/ui/segmented-control';
import AdminLayout from '@/layouts/admin-layout';
import { formatDayMonth, formatTime } from '@/lib/format';
import { useStaffCan } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { index as activityIndex } from '@/routes/admin/activity';
import { show as showClient } from '@/routes/admin/clients';
import { destroy, edit } from '@/routes/admin/clients/events';
import { index } from '@/routes/admin/events';
import type { Event, Option, Paginated } from '@/types';

type Filters = {
    state: string | null;
    search: string | null;
    period: string | null;
    registration_type: string | null;
};

type Period = 'upcoming' | 'past' | 'all';

export default function AdminEventsIndex({
    events,
    filters,
    states,
    registrationTypes,
}: {
    events: Paginated<Event>;
    filters: Filters;
    states: Option[];
    registrationTypes: Option[];
}) {
    const filter = (changes: Partial<Filters>) =>
        router.get(
            index.url(),
            // An explicit period (even "all") keeps the upcoming default from coming back.
            { ...filters, period: filters.period ?? 'all', ...changes },
            { preserveState: true, replace: true },
        );
    const period = (filters.period ?? 'all') as Period;

    return (
        <AdminLayout>
            <Head title="Events" />
            <PageHeader
                title="Events"
                description={`${events.meta.total.toLocaleString()} ${events.meta.total === 1 ? 'event' : 'events'}${filters.search ? ` matching “${filters.search}”` : ''}`}
            >
                <div className="flex flex-wrap items-center gap-2">
                    <SegmentedControl
                        label="When"
                        variant="box"
                        value={period}
                        onChange={(value) => filter({ period: value })}
                        items={[
                            { value: 'upcoming' as Period, label: 'Upcoming' },
                            { value: 'past' as Period, label: 'Past' },
                            { value: 'all' as Period, label: 'All' },
                        ]}
                    />
                    <FilterSelect
                        label="Registration"
                        value={filters.registration_type}
                        options={registrationTypes}
                        onChange={(value) =>
                            filter({ registration_type: value })
                        }
                    />
                    <FilterSelect
                        label="Status"
                        value={filters.state}
                        options={states}
                        onChange={(value) => filter({ state: value })}
                    />
                    {filters.search && (
                        <button
                            type="button"
                            onClick={() => filter({ search: null })}
                            className="flex h-9 items-center gap-1.5 rounded-md border border-dashed border-input bg-card px-3 text-[13px] text-muted-foreground"
                        >
                            Clear search
                        </button>
                    )}
                </div>
            </PageHeader>

            <div className="overflow-hidden rounded-lg bg-card shadow-card">
                {events.data.length === 0 ? (
                    <EmptyState icon={CalendarDays} title="No events found" />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[13px]">
                            <thead>
                                <tr className="bg-raised text-left text-[11px] tracking-[0.06em] text-muted-foreground">
                                    <th className="px-4 py-2.5 font-bold">
                                        EVENT
                                    </th>
                                    <th className="px-3 py-2.5 font-bold">
                                        CLIENT
                                    </th>
                                    <th className="px-3 py-2.5 font-bold">
                                        DATE
                                    </th>
                                    <th className="px-3 py-2.5 font-bold">
                                        REGISTRATION
                                    </th>
                                    <th className="px-3 py-2.5 text-right font-bold">
                                        HEADCOUNT
                                    </th>
                                    <th className="px-3 py-2.5 font-bold">
                                        MESSAGES
                                    </th>
                                    <th className="px-3 py-2.5 font-bold">
                                        STATUS
                                    </th>
                                    <th className="px-4 py-2.5">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {events.data.map((event) => (
                                    <EventRow key={event.id} event={event} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pagination meta={events.meta} />
            </div>
        </AdminLayout>
    );
}

/** "Registration: Any" style dropdown chip of the 3a filter row. */
function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string | null;
    options: Option[];
    onChange: (value: string | null) => void;
}) {
    return (
        <select
            aria-label={label}
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value || null)}
            className="h-9 rounded-md border border-input bg-card px-3 text-[13px] outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
        >
            <option value="">{label}: Any</option>
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {label}: {option.label}
                </option>
            ))}
        </select>
    );
}

function EventRow({ event }: { event: Event }) {
    const can = useStaffCan();
    const capacity = event.max_capacity || event.guest_limit;
    const failed = event.failed_messages_count ?? 0;
    const queued = event.queued_messages_count ?? 0;

    return (
        <tr className="border-t border-border even:bg-raised/60">
            <td className="px-4 py-3">
                {event.client && can('events.update') ? (
                    <Link
                        href={edit.url({
                            client: event.client.id,
                            event: event.id,
                        })}
                        className="font-semibold hover:underline"
                    >
                        {event.title}
                    </Link>
                ) : (
                    <span className="font-semibold">{event.title}</span>
                )}
                <div className="text-xs text-subtle">
                    {event.state === 'published' && event.public_url
                        ? event.public_url.replace(/^https?:\/\//, '')
                        : 'Not published'}
                </div>
            </td>
            <td className="px-3 py-3">
                {event.client &&
                    (can('clients.read') ? (
                        <Link
                            href={showClient.url(event.client.id)}
                            className="hover:underline"
                        >
                            {event.client.name}
                        </Link>
                    ) : (
                        event.client.name
                    ))}
            </td>
            <td className="px-3 py-3 whitespace-nowrap">
                {formatDayMonth(event.event_date, true)}
                {event.start_time && (
                    <div className="text-xs text-subtle">
                        {formatTime(event.start_time)}
                    </div>
                )}
            </td>
            <td className="px-3 py-3">{event.registration_type_label}</td>
            <td className="px-3 py-3 text-right font-bold whitespace-nowrap tabular-nums">
                {event.headcount ?? 0} / {capacity || '—'}
            </td>
            <td className="px-3 py-3 whitespace-nowrap">
                {failed > 0 ? (
                    <span className="inline-flex items-center gap-1 font-semibold text-destructive">
                        <AlertTriangle className="size-3.25" />
                        {failed} failed
                    </span>
                ) : queued > 0 ? (
                    <span className="text-muted-foreground">
                        {queued} queued
                    </span>
                ) : (
                    <span className="text-subtle">—</span>
                )}
            </td>
            <td className="px-3 py-3">
                <StatusBadge status={event.state} />
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center justify-end gap-1 text-muted-foreground">
                    {event.public_url && (
                        <a
                            href={event.public_url}
                            target="_blank"
                            rel="noreferrer"
                            aria-label={`Open ${event.title}'s public page`}
                            className="rounded-md p-1 hover:bg-raised hover:text-foreground"
                        >
                            <Eye className="size-4" />
                        </a>
                    )}
                    <RowMenu event={event} />
                </div>
            </td>
        </tr>
    );
}

/** Row actions; ones the staff member lacks the permission for show why. */
function RowMenu({ event }: { event: Event }) {
    const can = useStaffCan();
    const client = event.client;
    const confirmation = useConfirm();

    if (!client) {
        return null;
    }

    const remove = () =>
        confirmation.ask({
            title: `Delete "${event.title}" and all its guests?`,
            confirmLabel: 'Delete',
            onConfirm: () =>
                router.delete(
                    destroy.url({ client: client.id, event: event.id }),
                    { preserveScroll: true },
                ),
        });

    return (
        <span className="relative inline-flex">
            <ConfirmBar
                variant="pop"
                request={confirmation.request}
                onCancel={confirmation.cancel}
            />
            <DropdownMenu>
                <DropdownMenuTrigger
                    aria-label={`Actions for ${event.title}`}
                    className="rounded-md p-1 hover:bg-raised hover:text-foreground"
                >
                    <MoreHorizontal className="size-4" />
                </DropdownMenuTrigger>
                <DropdownMenuContent className="w-56">
                    {can('events.update') && (
                        <DropdownMenuItem
                            render={
                                <Link
                                    href={edit.url({
                                        client: client.id,
                                        event: event.id,
                                    })}
                                />
                            }
                        >
                            <Pencil /> Edit event
                        </DropdownMenuItem>
                    )}
                    {can('clients.read') && (
                        <DropdownMenuItem
                            render={<Link href={showClient.url(client.id)} />}
                        >
                            <UserRound /> View client
                        </DropdownMenuItem>
                    )}
                    {can('activity.read') && (
                        <DropdownMenuItem
                            render={
                                <Link
                                    href={activityIndex.url({
                                        query: { client: client.id },
                                    })}
                                />
                            }
                        >
                            <Activity /> View activity
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        disabled={!can('events.delete')}
                        onClick={remove}
                        variant="destructive"
                        className={cn(!can('events.delete') && 'items-start')}
                    >
                        {can('events.delete') ? (
                            <>
                                <Trash2 /> Delete event
                            </>
                        ) : (
                            <>
                                <Lock className="mt-0.5" />
                                <span>
                                    Delete event
                                    <span className="block text-[11px] text-muted-foreground">
                                        Requires manage events
                                    </span>
                                </span>
                            </>
                        )}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </span>
    );
}
