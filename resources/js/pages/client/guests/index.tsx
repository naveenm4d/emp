import { Head, router, usePoll } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
import { useState } from 'react';

import { EventTabs } from '@/components/events/event-tabs';
import { GuestActions } from '@/components/guests/guest-actions';
import { GuestForm } from '@/components/guests/guest-form';
import { GuestDetailsSheet } from '@/components/guests/guest-details-sheet';
import { rsvpDisplayStatus } from '@/components/rsvps/rsvp-status';
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
import { showsApproval } from '@/lib/guests';
import { cn } from '@/lib/utils';
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
    defaultInvitationMessage: string;
    defaultReminderMessage: string;
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
    defaultInvitationMessage,
    defaultReminderMessage,
    options,
}: Props) {
    const eventMessage = event.invitation_message ?? defaultInvitationMessage;
    const eventReminder = event.reminder_message ?? defaultReminderMessage;

    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<string | null>(null);

    // Picks up RSVP replies and WhatsApp delivery updates without a manual reload.
    usePoll(5000, { only: ['guests', 'summary'] });

    const approval = showsApproval(event, summary);

    // Looked up in the (polled) list, so the details panel and edit form stay current.
    const [selected, setSelected] = useState<string | null>(null);
    const selectedGuest =
        guests.data.find((guest) => guest.id === selected) ?? null;
    const editingGuest =
        guests.data.find((guest) => guest.id === editing) ?? null;

    /** Opens the edit form above the list and brings it into view (it may be off-screen on phones). */
    const startEditing = (guestId: string) => {
        setSelected(null);
        setEditing(guestId);
        requestAnimationFrame(() =>
            document
                .getElementById('guest-edit')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' }),
        );
    };

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
                description={
                    approval
                        ? `${summary.total} guests · ${summary.approved} approved · ${summary.pending} pending · ${summary.waitlisted} waitlisted`
                        : `${summary.total} guests`
                }
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
                            defaultMessage={eventMessage}
                            defaultReminderMessage={eventReminder}
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

            {editingGuest && (
                <Card id="guest-edit" className="mb-4 scroll-mt-4">
                    <CardContent className="space-y-3">
                        <p className="text-sm font-medium">
                            Edit {editingGuest.name}
                        </p>
                        <GuestForm
                            key={editingGuest.id}
                            guest={editingGuest}
                            defaultMessage={eventMessage}
                            defaultReminderMessage={eventReminder}
                            submitLabel="Save"
                            onCancel={() => setEditing(null)}
                            onSubmit={(form) =>
                                form.patch(update.url(editingGuest), {
                                    preserveScroll: true,
                                    onSuccess: () => setEditing(null),
                                })
                            }
                        />
                    </CardContent>
                </Card>
            )}

            <div className="mb-4 flex flex-wrap gap-2">
                <Input
                    className="w-full sm:max-w-xs"
                    placeholder="Search name, email or phone…"
                    defaultValue={filters.search ?? ''}
                    onKeyDown={(e) =>
                        e.key === 'Enter' &&
                        filter({ search: e.currentTarget.value || null })
                    }
                />
                {approval && (
                    <FilterSelect
                        label="All approvals"
                        value={filters.approval_status}
                        options={options.approval_statuses}
                        onChange={(v) => filter({ approval_status: v })}
                    />
                )}
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
                    <>
                        {/* Phones: one tappable card per guest */}
                        <ul className="divide-y divide-border md:hidden">
                            {guests.data.map((guest) => (
                                <li
                                    key={guest.id}
                                    className="flex items-start gap-3 p-4"
                                >
                                    <button
                                        type="button"
                                        className="min-w-0 flex-1 text-left"
                                        onClick={() => setSelected(guest.id)}
                                    >
                                        <p className="truncate font-medium">
                                            {guest.name}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {contactLine(guest)}
                                        </p>
                                        <GuestBadges
                                            guest={guest}
                                            approval={approval}
                                            className="mt-2"
                                        />
                                    </button>
                                    <GuestActions
                                        guest={guest}
                                        registrationType={
                                            event.registration_type
                                        }
                                        onEdit={() => startEditing(guest.id)}
                                    />
                                </li>
                            ))}
                        </ul>

                        {/* Tablets and up: a table; click a row for details */}
                        <Table className="hidden md:table">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-4">
                                        Guest
                                    </TableHead>
                                    {approval && (
                                        <TableHead>Approval</TableHead>
                                    )}
                                    <TableHead>RSVP</TableHead>
                                    <TableHead>RSVP link</TableHead>
                                    <TableHead>Message</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Source
                                    </TableHead>
                                    <TableHead className="pr-4 text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {guests.data.map((guest) => (
                                    <TableRow
                                        key={guest.id}
                                        role="button"
                                        tabIndex={0}
                                        aria-label={`Show details for ${guest.name}`}
                                        className="cursor-pointer"
                                        onClick={() => setSelected(guest.id)}
                                        onKeyDown={(e) => {
                                            if (
                                                e.target === e.currentTarget &&
                                                (e.key === 'Enter' ||
                                                    e.key === ' ')
                                            ) {
                                                e.preventDefault();
                                                setSelected(guest.id);
                                            }
                                        }}
                                    >
                                        <TableCell className="pl-4">
                                            <p className="font-medium">
                                                {guest.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {contactLine(guest)}
                                            </p>
                                        </TableCell>
                                        {approval && (
                                            <TableCell>
                                                <StatusBadge
                                                    status={
                                                        guest.approval_status
                                                    }
                                                />
                                            </TableCell>
                                        )}
                                        <TableCell>
                                            <StatusBadge
                                                status={guest.rsvp_status}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <OptionalBadge
                                                status={
                                                    guest.latest_rsvp &&
                                                    rsvpDisplayStatus(
                                                        guest.latest_rsvp,
                                                    )
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <OptionalBadge
                                                status={
                                                    guest.latest_rsvp?.message
                                                        ?.status
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="hidden text-xs text-muted-foreground lg:table-cell">
                                            {sourceLabels[guest.source]}
                                        </TableCell>
                                        <TableCell
                                            className="pr-4"
                                            onClick={(e) => e.stopPropagation()}
                                            onKeyDown={(e) =>
                                                e.stopPropagation()
                                            }
                                        >
                                            <GuestActions
                                                guest={guest}
                                                registrationType={
                                                    event.registration_type
                                                }
                                                onEdit={() =>
                                                    startEditing(guest.id)
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </>
                )}
                <Pagination meta={guests.meta} />
            </div>

            <GuestDetailsSheet
                guest={selectedGuest}
                onClose={() => setSelected(null)}
                registrationType={event.registration_type}
                showsApproval={approval}
                onEdit={(guest) => startEditing(guest.id)}
            />
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
            className="min-w-0 flex-1 sm:w-40 sm:flex-none"
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

const sourceLabels: Record<Guest['source'], string> = {
    manual: 'Added by you',
    public_link: 'Public link',
    import: 'Imported',
    api: 'API',
};

function contactLine(guest: Guest): string {
    return (
        [guest.phone, guest.email].filter(Boolean).join(' · ') ||
        'No contact details'
    );
}

function OptionalBadge({ status }: { status?: string | null }) {
    return status ? (
        <StatusBadge status={status} />
    ) : (
        <span className="text-xs text-muted-foreground">—</span>
    );
}

/** The key statuses as badges, for the phone card layout. */
function GuestBadges({
    guest,
    approval,
    className,
}: {
    guest: Guest;
    approval: boolean;
    className?: string;
}) {
    const rsvp = guest.latest_rsvp;

    return (
        <div className={cn('flex flex-wrap gap-1.5', className)}>
            {approval && <StatusBadge status={guest.approval_status} />}
            <StatusBadge status={guest.rsvp_status} />
            {rsvp?.message && (
                <StatusBadge
                    status={rsvp.message.status}
                    label={`Message ${rsvp.message.status}`}
                />
            )}
        </div>
    );
}
