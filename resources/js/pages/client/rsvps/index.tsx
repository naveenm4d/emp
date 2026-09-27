import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useState } from 'react';

import { GuestDetailsSheet } from '@/components/guests/guest-details-sheet';
import type { GuestDetailsData } from '@/components/guests/guest-timeline';
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
import { RsvpPreview } from '@/components/plans/feature-previews';
import { LockedEventPage } from '@/components/plans/locked-feature';
import EventLayout from '@/layouts/event-layout';
import { loadGuestDetails, openGuestId } from '@/lib/guests';
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
    locked?: undefined;
    rsvps: Paginated<Rsvp>;
    summary: RsvpSummary;
    filters: { status: string | null };
    /** History and answers of the guest open in the panel (?guest=). */
    guestDetails?: GuestDetailsData | null;
    statuses: Option[];
};

/** Not in the client's plan: the controller sends only the event. */
type LockedProps = { event: Resource<Event>; locked: true };

export default function RsvpsIndex(props: Props | LockedProps) {
    if (props.locked) {
        return (
            <LockedEventPage
                event={props.event.data}
                title="RSVPs"
                feature="rsvp_list"
                preview={<RsvpPreview />}
            />
        );
    }

    return <RsvpsPage {...props} />;
}

function RsvpsPage({
    event: { data: event },
    rsvps,
    summary,
    filters,
    guestDetails,
    statuses,
}: Props) {
    // Picks up replies and WhatsApp delivery updates without a manual reload.
    usePoll(5000, { only: ['rsvps', 'summary', 'guestDetails'] });

    // The details panel shows the guest with the clicked RSVP link, looked up
    // in the (polled) list so it stays current.
    const { url } = usePage();
    const [selected, setSelected] = useState<string | null>(() => {
        const guestId = openGuestId(url);

        return (
            rsvps.data.find((rsvp) => rsvp.guest?.id === guestId)?.id ?? null
        );
    });
    const select = (rsvp: Rsvp | null) => {
        setSelected(rsvp?.id ?? null);
        loadGuestDetails(rsvp?.guest?.id ?? null);
    };
    const selectedRsvp = rsvps.data.find((rsvp) => rsvp.id === selected);
    const selectedGuest: Guest | null = selectedRsvp?.guest
        ? { ...selectedRsvp.guest, latest_rsvp: selectedRsvp }
        : null;

    return (
        <EventLayout event={event}>
            <Head title={`RSVPs · ${event.title}`} />
            <PageHeader
                eyebrow={event.title}
                title="RSVPs"
                description={`${summary.total} RSVP links · ${summary.sent} awaiting reply · ${summary.accepted} accepted · ${summary.declined} declined · ${summary.maybe} maybe · ${summary.expired} expired`}
            />

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

            <div className="overflow-hidden rounded-lg bg-card shadow-card">
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
                                        onClick={() => select(rsvp)}
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
                                    <RsvpActions
                                        rsvp={rsvp}
                                        messageLimits={event.message_limits}
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
                                        onClick={() => select(rsvp)}
                                        onKeyDown={(e) => {
                                            if (
                                                e.target === e.currentTarget &&
                                                (e.key === 'Enter' ||
                                                    e.key === ' ')
                                            ) {
                                                e.preventDefault();
                                                select(rsvp);
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
                                            <RsvpActions
                                                rsvp={rsvp}
                                                messageLimits={
                                                    event.message_limits
                                                }
                                            />
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
                details={guestDetails}
                onClose={() => select(null)}
                registrationType={event.registration_type}
                messageLimits={event.message_limits}
                showsApproval={event.registration_type === 'approval_required'}
                onEdit={(guest) =>
                    router.visit(guestsIndex.url(event), {
                        data: { search: guest.name },
                    })
                }
            />
        </EventLayout>
    );
}
