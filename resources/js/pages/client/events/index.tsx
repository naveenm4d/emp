import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, Plus } from 'lucide-react';

import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { StatusBadge } from '@/components/shared/status-badge';
import { PlanStrip } from '@/components/plans/plan-strip';
import { Button, buttonVariants } from '@/components/ui/button';
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
import ClientLayout from '@/layouts/client-layout';
import { formatDate } from '@/lib/format';
import { useClientPlan } from '@/lib/plans';
import { create, index, show } from '@/routes/client/events';
import type { Event, Option, Paginated } from '@/types';

type Props = {
    events: Paginated<Event>;
    filters: { state: string | null; search: string | null };
    states: Option[];
};

export default function EventsIndex({ events, filters, states }: Props) {
    const plan = useClientPlan();
    const filter = (changes: Partial<Props['filters']>) =>
        router.get(
            index.url(),
            { ...filters, ...changes },
            { preserveState: true, replace: true },
        );

    return (
        <ClientLayout>
            <Head title="Events" />
            <PageHeader
                title="Events"
                description="Everything you are hosting."
                actions={
                    plan && !plan.can_create_event ? (
                        <Button disabled title={plan.reason ?? undefined}>
                            <Plus /> New event
                        </Button>
                    ) : (
                        <Link href={create.url()} className={buttonVariants()}>
                            <Plus /> New event
                        </Link>
                    )
                }
            />
            <PlanStrip />

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
                    <EmptyState
                        icon={CalendarDays}
                        title="No events yet"
                        description="Create your first event to start inviting guests."
                        action={
                            <Link
                                href={create.url()}
                                className={buttonVariants({ size: 'sm' })}
                            >
                                New event
                            </Link>
                        }
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-4">Event</TableHead>
                                <TableHead>State</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Registration</TableHead>
                                <TableHead className="pr-4 text-right">
                                    Guests
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {events.data.map((event) => (
                                <TableRow
                                    key={event.id}
                                    className="cursor-pointer"
                                    onClick={() =>
                                        router.visit(show.url(event))
                                    }
                                >
                                    <TableCell className="pl-4">
                                        <p className="font-medium">
                                            {event.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            /{event.slug}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={event.state} />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(event.event_date)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {event.registration_type ===
                                        'guest_list_only'
                                            ? 'Guest list only'
                                            : event.registration_open
                                              ? 'Open'
                                              : 'Closed'}
                                    </TableCell>
                                    <TableCell className="pr-4 text-right tabular-nums">
                                        {event.guests_count}
                                        {event.max_capacity > 0 && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                / {event.max_capacity}
                                            </span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination meta={events.meta} />
            </div>
        </ClientLayout>
    );
}
