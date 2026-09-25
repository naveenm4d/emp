import { Head, router } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';

import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { StatusBadge } from '@/components/shared/status-badge';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import InternalLayout from '@/layouts/internal-layout';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/internal/events';
import type { Event, Option, Paginated } from '@/types';

type Filters = { state: string | null; search: string | null };

export default function InternalEventsIndex({
    events,
    filters,
    states,
}: {
    events: Paginated<Event>;
    filters: Filters;
    states: Option[];
}) {
    const filter = (changes: Partial<Filters>) =>
        router.get(
            index.url(),
            { ...filters, ...changes },
            { preserveState: true, replace: true },
        );

    return (
        <InternalLayout>
            <Head title="Events" />
            <PageHeader
                title="Events"
                description="Every event across all clients."
            />

            <div className="mb-4 flex flex-wrap gap-2">
                <Input
                    className="max-w-xs"
                    placeholder="Search title or slug…"
                    defaultValue={filters.search ?? ''}
                    onKeyDown={(e) =>
                        e.key === 'Enter' &&
                        filter({ search: e.currentTarget.value || null })
                    }
                />
                <Select
                    className="w-40"
                    value={filters.state ?? ''}
                    onChange={(e) => filter({ state: e.target.value || null })}
                >
                    <option value="">All states</option>
                    {states.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </Select>
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-card">
                {events.data.length === 0 ? (
                    <EmptyState icon={CalendarDays} title="No events found" />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-4">Event</TableHead>
                                <TableHead>Client</TableHead>
                                <TableHead>State</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead className="pr-4 text-right">
                                    Guests
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {events.data.map((event) => (
                                <TableRow key={event.id}>
                                    <TableCell className="pl-4">
                                        <p className="font-medium">
                                            {event.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            /{event.slug}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <p>{event.client?.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {event.client?.email}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={event.state} />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(event.event_date)}
                                    </TableCell>
                                    <TableCell className="pr-4 text-right tabular-nums">
                                        {event.guests_count}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination meta={events.meta} />
            </div>
        </InternalLayout>
    );
}
