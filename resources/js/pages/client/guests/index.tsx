import { Head, router } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
import { Fragment, useState } from 'react';

import { EventTabs } from '@/components/events/event-tabs';
import { GuestActions } from '@/components/guests/guest-actions';
import { GuestForm } from '@/components/guests/guest-form';
import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { index, store } from '@/routes/client/events/guests';
import { update } from '@/routes/client/guests';
import type {
    Event,
    Guest,
    GuestSummary,
    Option,
    Paginated,
    Resource,
} from '@/types';

type Filters = {
    source: string | null;
    approval_status: string | null;
    rsvp_status: string | null;
    search: string | null;
};

type Props = {
    event: Resource<Event>;
    guests: Paginated<Guest>;
    summary: GuestSummary;
    filters: Filters;
    options: {
        sources: Option[];
        approval_statuses: Option[];
        rsvp_statuses: Option[];
    };
};

export default function GuestsIndex({
    event: { data: event },
    guests,
    summary,
    filters,
    options,
}: Props) {
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<string | null>(null);

    const filter = (changes: Partial<Filters>) =>
        router.get(
            index.url(event),
            { ...filters, ...changes },
            { preserveState: true, replace: true },
        );

    return (
        <ClientLayout>
            <Head title={`Guests · ${event.title}`} />
            <PageHeader
                title={event.title}
                description={`${summary.total} guests · ${summary.approved} approved · ${summary.pending} pending · ${summary.waitlisted} waitlisted`}
                actions={
                    event.state !== 'cancelled' && (
                        <Button onClick={() => setAdding(true)}>
                            <Plus /> Add guest
                        </Button>
                    )
                }
            />
            <EventTabs event={event} />

            {adding && (
                <Card className="mb-4">
                    <CardContent>
                        <GuestForm
                            submitLabel="Add"
                            onCancel={() => setAdding(false)}
                            onSubmit={(form) =>
                                form.post(store.url(event), {
                                    preserveScroll: true,
                                    onSuccess: () => form.reset(),
                                })
                            }
                        />
                    </CardContent>
                </Card>
            )}

            <div className="mb-4 flex flex-wrap gap-2">
                <Input
                    className="max-w-xs"
                    placeholder="Search name, email or phone…"
                    defaultValue={filters.search ?? ''}
                    onKeyDown={(e) =>
                        e.key === 'Enter' &&
                        filter({ search: e.currentTarget.value || null })
                    }
                />
                <FilterSelect
                    label="All approvals"
                    value={filters.approval_status}
                    options={options.approval_statuses}
                    onChange={(v) => filter({ approval_status: v })}
                />
                <FilterSelect
                    label="All RSVPs"
                    value={filters.rsvp_status}
                    options={options.rsvp_statuses}
                    onChange={(v) => filter({ rsvp_status: v })}
                />
                <FilterSelect
                    label="All sources"
                    value={filters.source}
                    options={options.sources}
                    onChange={(v) => filter({ source: v })}
                />
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-card">
                {guests.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="No guests found"
                        description="Add guests manually or open public registration."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-4">Guest</TableHead>
                                <TableHead>Approval</TableHead>
                                <TableHead>RSVP</TableHead>
                                <TableHead>Invitation</TableHead>
                                <TableHead>Source</TableHead>
                                <TableHead className="pr-4 text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {guests.data.map((guest) => (
                                <Fragment key={guest.id}>
                                    <TableRow>
                                        <TableCell className="pl-4">
                                            <p className="font-medium">
                                                {guest.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {[guest.email, guest.phone]
                                                    .filter(Boolean)
                                                    .join(' · ') ||
                                                    'No contact details'}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={guest.approval_status}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={guest.rsvp_status}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {guest.latest_invitation ? (
                                                <StatusBadge
                                                    status={
                                                        guest.latest_invitation
                                                            .status
                                                    }
                                                />
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {guest.source.replace('_', ' ')}
                                        </TableCell>
                                        <TableCell className="pr-4">
                                            <GuestActions
                                                guest={guest}
                                                onEdit={() =>
                                                    setEditing(
                                                        editing === guest.id
                                                            ? null
                                                            : guest.id,
                                                    )
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                    {editing === guest.id && (
                                        <TableRow className="hover:bg-transparent">
                                            <TableCell
                                                colSpan={6}
                                                className="bg-muted/30 px-4 py-4 whitespace-normal"
                                            >
                                                <GuestForm
                                                    guest={guest}
                                                    submitLabel="Save"
                                                    onCancel={() =>
                                                        setEditing(null)
                                                    }
                                                    onSubmit={(form) =>
                                                        form.patch(
                                                            update.url(guest),
                                                            {
                                                                preserveScroll: true,
                                                                onSuccess: () =>
                                                                    setEditing(
                                                                        null,
                                                                    ),
                                                            },
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </Fragment>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination meta={guests.meta} />
            </div>
        </ClientLayout>
    );
}

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
        <Select
            className="w-40"
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value || null)}
        >
            <option value="">{label}</option>
            {options.map((o) => (
                <option key={o.value} value={o.value}>
                    {o.label}
                </option>
            ))}
        </Select>
    );
}
