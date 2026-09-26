import { Head, router, usePoll } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useState } from 'react';

import { EventTabs } from '@/components/events/event-tabs';
import { GuestDetailsSheet } from '@/components/guests/guest-details-sheet';
import { RsvpActions } from '@/components/rsvps/rsvp-actions';
import { rsvpDisplayStatus } from '@/components/rsvps/rsvp-status';
import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { StatusBadge } from '@/components/shared/status-badge';
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
import { index as guestsIndex } from '@/routes/client/events/guests';
import { index } from '@/routes/client/events/rsvps';
import type {
    Event,
    Guest,
    Option,
    Paginated,
    Resource,
    Rsvp,
    RsvpSummary,
} from '@/types';

type Props = {
    event: Resource<Event>;
    rsvps: Paginated<Rsvp>;
    summary: RsvpSummary;
    filters: { status: string | null };
    statuses: Option[];
};

export default function RsvpsIndex({
    event: { data: event },
    rsvps,
    summary,
    filters,
    statuses,
}: Props) {
    // Picks up replies and WhatsApp delivery updates without a manual reload.
    usePoll(5000, { only: ['rsvps', 'summary'] });

    // The details panel shows the guest with the clicked RSVP link, looked up
    // in the (polled) list so it stays current.
    const [selected, setSelected] = useState<string | null>(null);
    const selectedRsvp = rsvps.data.find((rsvp) => rsvp.id === selected);
    const selectedGuest: Guest | null = selectedRsvp?.guest
        ? { ...selectedRsvp.guest, latest_rsvp: selectedRsvp }
        : null;

    return (
        <ClientLayout>
            <Head title={`RSVPs · ${event.title}`} />
            <PageHeader
                title={event.title}
                description={`${summary.total} RSVP links · ${summary.sent} awaiting reply · ${summary.accepted} accepted · ${summary.declined} declined · ${summary.expired} expired`}
            />
            <EventTabs event={event} />

            <div className="mb-4">
                <Select
                    className="w-full sm:w-44"
                    value={filters.status ?? ''}
                    onChange={(e) =>
                        router.get(
                            index.url(event),
                            { status: e.target.value || null },
                            { preserveState: true, replace: true },
                        )
                    }
                >
                    <option value="">All statuses</option>
                    {statuses.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </Select>
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-card">
                {rsvps.data.length === 0 ? (
                    <EmptyState
                        icon={Mail}
                        title="No RSVP links yet"
                        description="Send RSVP links to approved guests from the Guests tab."
                    />
                ) : (
                    <>
                        {/* Phones: one tappable card per RSVP link */}
                        <ul className="divide-y divide-border md:hidden">
                            {rsvps.data.map((rsvp) => (
                                <li
                                    key={rsvp.id}
                                    className="flex items-start gap-3 p-4"
                                >
                                    <button
                                        type="button"
                                        className="min-w-0 flex-1 text-left"
                                        onClick={() => setSelected(rsvp.id)}
                                    >
                                        <p className="truncate font-medium">
                                            {rsvp.guest?.name}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {rsvp.guest?.phone ?? 'No phone'}
                                        </p>
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            <StatusBadge
                                                status={rsvpDisplayStatus(rsvp)}
                                            />
                                            {rsvp.message && (
                                                <StatusBadge
                                                    status={rsvp.message.status}
                                                    label={`Message ${rsvp.message.status}`}
                                                />
                                            )}
                                        </div>
                                    </button>
                                    <RsvpActions rsvp={rsvp} />
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
                                    <TableHead>Status</TableHead>
                                    <TableHead>Message</TableHead>
                                    <TableHead className="pr-4 text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rsvps.data.map((rsvp) => (
                                    <TableRow
                                        key={rsvp.id}
                                        role="button"
                                        tabIndex={0}
                                        aria-label={`Show details for ${rsvp.guest?.name ?? 'this guest'}`}
                                        className="cursor-pointer"
                                        onClick={() => setSelected(rsvp.id)}
                                        onKeyDown={(e) => {
                                            if (
                                                e.target === e.currentTarget &&
                                                (e.key === 'Enter' ||
                                                    e.key === ' ')
                                            ) {
                                                e.preventDefault();
                                                setSelected(rsvp.id);
                                            }
                                        }}
                                    >
                                        <TableCell className="pl-4">
                                            <p className="font-medium">
                                                {rsvp.guest?.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {rsvp.guest?.phone ??
                                                    'No phone'}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={rsvpDisplayStatus(rsvp)}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {rsvp.message ? (
                                                <StatusBadge
                                                    status={rsvp.message.status}
                                                />
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell
                                            className="pr-4"
                                            onClick={(e) => e.stopPropagation()}
                                            onKeyDown={(e) =>
                                                e.stopPropagation()
                                            }
                                        >
                                            <RsvpActions rsvp={rsvp} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </>
                )}
                <Pagination meta={rsvps.meta} />
            </div>

            <GuestDetailsSheet
                guest={selectedGuest}
                onClose={() => setSelected(null)}
                registrationType={event.registration_type}
                showsApproval={event.registration_type === 'approval_required'}
                onEdit={(guest) =>
                    router.visit(guestsIndex.url(event), {
                        data: { search: guest.name },
                    })
                }
            />
        </ClientLayout>
    );
}
