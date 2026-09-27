import { Head, router, usePage, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    CircleDashed,
    Search,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';

import { GuestActions } from '@/components/guests/guest-actions';
import { GuestFormDialog } from '@/components/guests/guest-form';
import { GuestDetailsSheet } from '@/components/guests/guest-details-sheet';
import type { GuestDetailsData } from '@/components/guests/guest-timeline';
import { rsvpDisplayStatus } from '@/components/rsvps/rsvp-status';
import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import EventLayout from '@/layouts/event-layout';
import { initials, timeAgo } from '@/lib/format';
import {
    invitationState,
    loadGuestDetails,
    openGuestId,
    showsApproval,
} from '@/lib/guests';
import { cn } from '@/lib/utils';
import { index, store } from '@/routes/client/events/guests';
import { approve, reject, update } from '@/routes/client/guests';
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
    /** History and answers of the guest open in the panel (?guest=). */
    guestDetails?: GuestDetailsData | null;
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
    guestDetails,
    defaultInvitationMessage,
    defaultReminderMessage,
    options,
}: Props) {
    const eventMessage = event.invitation_message ?? defaultInvitationMessage;
    const eventReminder = event.reminder_message ?? defaultReminderMessage;

    const [adding, setAdding] = useState(false);
    // A fresh add form each time the popup opens.
    const [addKey, setAddKey] = useState(0);
    const [editing, setEditing] = useState<string | null>(null);

    // Picks up RSVP replies and WhatsApp delivery updates without a manual reload.
    usePoll(5000, { only: ['guests', 'summary', 'guestDetails'] });

    const approval = showsApproval(event, summary);

    // Looked up in the (polled) list, so the details panel and edit form stay current.
    const { url } = usePage();
    const [selected, setSelected] = useState<string | null>(() =>
        openGuestId(url),
    );
    const select = (guestId: string | null) => {
        if (guestId === selected) {
            return;
        }

        setSelected(guestId);
        loadGuestDetails(guestId);
    };
    const selectedGuest =
        guests.data.find((guest) => guest.id === selected) ?? null;
    const editingGuest =
        guests.data.find((guest) => guest.id === editing) ?? null;

    /** Closes the details panel and opens the edit popup. */
    const startEditing = (guestId: string) => {
        select(null);
        setEditing(guestId);
    };

    const filter = (changes: Partial<Filters>) =>
        router.get(
            index.url(event),
            { ...filters, ...changes },
            { preserveState: true, replace: true },
        );

    // Against the plan's limit (plus extra guests bought), when there is one.
    const guestCount =
        event.guest_limit === null
            ? `${summary.total} guests`
            : `${summary.total} / ${event.guest_limit} guests`;

    return (
        <EventLayout event={event}>
            <Head title={`Guests · ${event.title}`} />
            <PageHeader
                eyebrow={event.title}
                title="Guests"
                description={
                    <span className="hidden md:inline">
                        {(approval
                            ? `${guestCount} · ${summary.approved} approved · ${summary.pending} pending · ${summary.waitlisted} waitlisted`
                            : guestCount) +
                            ` · headcount ${summary.headcount} confirmed, ${summary.headcount_total} expected`}
                    </span>
                }
                actions={
                    event.state !== 'cancelled' && (
                        <Button
                            size="lg"
                            onClick={() => {
                                setAddKey((key) => key + 1);
                                setAdding(true);
                            }}
                            className="fixed right-4 bottom-24 z-30 h-13 rounded-full px-5 text-sm shadow-[0_6px_14px_color-mix(in_srgb,var(--primary)_35%,transparent)] md:static md:h-10 md:rounded-md md:shadow-none"
                        >
                            <UserPlus /> Add guest
                        </Button>
                    )
                }
            />

            <GuestFormDialog
                key={`add-${addKey}`}
                open={adding}
                onOpenChange={setAdding}
                subtitle={event.title}
                defaultMessage={eventMessage}
                defaultReminderMessage={eventReminder}
                onSubmit={(form, done) =>
                    form.post(store.url(event), {
                        preserveScroll: true,
                        onSuccess: (page) => !page.props.flash.error && done(),
                    })
                }
            />

            {editingGuest && (
                <GuestFormDialog
                    key={editingGuest.id}
                    open
                    onOpenChange={(open) => !open && setEditing(null)}
                    guest={editingGuest}
                    subtitle={event.title}
                    defaultMessage={eventMessage}
                    defaultReminderMessage={eventReminder}
                    onSubmit={(form, done) =>
                        form.patch(update.url(editingGuest), {
                            preserveScroll: true,
                            onSuccess: (page) =>
                                !page.props.flash.error && done(),
                        })
                    }
                />
            )}

            <div className="mb-3 flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center">
                <label className="relative md:w-72">
                    <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4.5 -translate-y-1/2 text-subtle" />
                    <input
                        type="search"
                        className="h-11 w-full rounded-xl bg-card pr-3 pl-10 text-sm shadow-card outline-none placeholder:text-subtle focus-visible:ring-3 focus-visible:ring-ring/50 md:h-10"
                        placeholder="Name, phone or email"
                        defaultValue={filters.search ?? ''}
                        onKeyDown={(e) =>
                            e.key === 'Enter' &&
                            filter({ search: e.currentTarget.value || null })
                        }
                    />
                </label>
                <div className="-mx-3 flex scrollbar-thin gap-2 overflow-x-auto px-3 md:mx-0 md:px-0">
                    {chips(summary, approval).map((chip) => {
                        const active =
                            filters.approval_status ===
                                (chip.filters.approval_status ?? null) &&
                            filters.rsvp_status ===
                                (chip.filters.rsvp_status ?? null);

                        return (
                            <FilterChip
                                key={chip.label}
                                active={active}
                                count={chip.count}
                                highlight={chip.highlight}
                                onClick={() =>
                                    filter({
                                        approval_status: null,
                                        rsvp_status: null,
                                        ...chip.filters,
                                    })
                                }
                            >
                                {chip.label}
                            </FilterChip>
                        );
                    })}
                </div>
                <div className="hidden md:ml-auto md:block">
                    <FilterSelect
                        label="All sources"
                        value={filters.source}
                        options={options.sources}
                        onChange={(v) => filter({ source: v })}
                    />
                </div>
            </div>

            <div className="-mx-3 overflow-hidden bg-card md:mx-0 md:rounded-lg md:shadow-card">
                {guests.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="No guests found"
                        description="Add guests manually or open public registration."
                    />
                ) : (
                    <>
                        {/* Phones (1c): approvals first, then everyone; tap for details */}
                        <MobileGuestList
                            guests={guests.data}
                            approval={approval}
                            onSelect={select}
                        />

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
                                        onClick={() => select(guest.id)}
                                        onKeyDown={(e) => {
                                            if (
                                                e.target === e.currentTarget &&
                                                (e.key === 'Enter' ||
                                                    e.key === ' ')
                                            ) {
                                                e.preventDefault();
                                                select(guest.id);
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
                                            <PartySize guest={guest} />
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
                                                messageLimits={
                                                    event.message_limits
                                                }
                                                onView={() => select(guest.id)}
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
                details={guestDetails}
                onClose={() => select(null)}
                registrationType={event.registration_type}
                messageLimits={event.message_limits}
                showsApproval={approval}
                onEdit={(guest) => startEditing(guest.id)}
            />
        </EventLayout>
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

/**
 * "+2" when the guest brings plus-ones or children, with what they were
 * invited with when their answer differs. Nothing once they declined.
 */
function PartySize({ guest }: { guest: Guest }) {
    if (guest.rsvp_status === 'declined') {
        return null;
    }

    const invited =
        guest.invited_additional_guests === null &&
        guest.invited_children === null
            ? null
            : (guest.invited_additional_guests ?? 0) +
              (guest.invited_children ?? 0);
    const bringing = guest.party_size - 1;
    const changed =
        invited !== null &&
        invited !== bringing &&
        ['confirmed', 'maybe'].includes(guest.rsvp_status);

    if (bringing === 0 && !changed) {
        return null;
    }

    return (
        <span
            className="ml-1.5 text-xs text-muted-foreground"
            title={`Party of ${guest.party_size}`}
        >
            +{bringing}
            {changed && ` (invited +${invited})`}
        </span>
    );
}

type Chip = {
    label: string;
    count: number;
    filters: Partial<Filters>;
    highlight?: boolean;
};

/** The quick filters of 1c, with their counts from the summary. */
function chips(summary: GuestSummary, approval: boolean): Chip[] {
    return [
        { label: 'All', count: summary.total, filters: {} },
        ...(approval
            ? [
                  {
                      label: 'To approve',
                      count: summary.pending,
                      filters: { approval_status: 'pending' },
                      highlight: summary.pending > 0,
                  },
              ]
            : []),
        {
            label: 'Attending',
            count: summary.rsvp_confirmed,
            filters: { rsvp_status: 'confirmed' },
        },
        {
            label: 'No reply',
            count: summary.rsvp_pending,
            filters: { rsvp_status: 'pending' },
        },
        {
            label: 'Declined',
            count: summary.rsvp_declined,
            filters: { rsvp_status: 'declined' },
        },
        {
            label: 'Not invited',
            count: summary.rsvp_not_sent,
            filters: { rsvp_status: 'not_sent' },
        },
    ];
}

function FilterChip({
    active,
    count,
    highlight,
    onClick,
    children,
}: {
    active: boolean;
    count: number;
    highlight?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={cn(
                'inline-flex h-8 shrink-0 items-center gap-1.5 rounded-full px-3 text-xs font-semibold whitespace-nowrap',
                active
                    ? 'bg-strong text-strong-foreground'
                    : 'border border-input',
            )}
        >
            {children}
            {highlight && !active ? (
                <span className="rounded-full bg-primary px-1.5 py-px text-[10px] text-primary-foreground">
                    {count}
                </span>
            ) : (
                <span className={active ? 'opacity-70' : 'text-subtle'}>
                    {count}
                </span>
            )}
        </button>
    );
}

const replyPills: Record<
    Guest['rsvp_status'],
    { label: string; icon: LucideIcon; className: string }
> = {
    confirmed: {
        label: 'Attending',
        icon: Check,
        className: 'bg-success-muted text-success',
    },
    declined: {
        label: 'Declined',
        icon: X,
        className: 'bg-destructive-muted text-destructive',
    },
    maybe: {
        label: 'Maybe',
        icon: CircleDashed,
        className: 'bg-warning-muted text-warning',
    },
    pending: {
        label: 'No reply',
        icon: CircleDashed,
        className: 'bg-foreground/6 text-muted-foreground',
    },
    not_sent: {
        label: 'Not invited',
        icon: CircleDashed,
        className: 'bg-foreground/6 text-muted-foreground',
    },
};

/** "Party of 4 · 1 child", "Invite opened", "Invitation failed"… */
function guestLine(guest: Guest): { text: string; failed: boolean } {
    if (guest.rsvp_status === 'confirmed' || guest.rsvp_status === 'maybe') {
        const children =
            guest.children > 0
                ? ` · ${guest.children} ${guest.children === 1 ? 'child' : 'children'}`
                : '';

        return {
            text: `Party of ${guest.party_size}${children}`,
            failed: false,
        };
    }

    if (guest.rsvp_status === 'pending') {
        const state = invitationState(guest);

        return { text: state.label, failed: state.failed };
    }

    return { text: contactLine(guest), failed: false };
}

function MobileGuestList({
    guests,
    approval,
    onSelect,
}: {
    guests: Guest[];
    approval: boolean;
    onSelect: (guestId: string) => void;
}) {
    const toApprove = approval
        ? guests.filter((guest) => guest.approval_status === 'pending')
        : [];
    const others = guests.filter((guest) => !toApprove.includes(guest));
    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });

    return (
        <div className="md:hidden">
            {toApprove.length > 0 && (
                <>
                    <GroupLabel>Waiting for approval</GroupLabel>
                    {toApprove.map((guest) => (
                        <div
                            key={guest.id}
                            className="flex items-center gap-3 border-b border-border px-4 py-3"
                        >
                            <button
                                type="button"
                                onClick={() => onSelect(guest.id)}
                                className="flex min-w-0 flex-1 items-center gap-3 text-left"
                            >
                                <Avatar name={guest.name} accent />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-semibold">
                                        {guest.name}
                                    </span>
                                    <span className="block truncate text-xs text-subtle">
                                        Party of {guest.party_size} · registered{' '}
                                        {timeAgo(guest.created_at)}
                                    </span>
                                </span>
                            </button>
                            <button
                                type="button"
                                aria-label={`Reject ${guest.name}`}
                                onClick={() => post(reject.url(guest))}
                                className="flex size-9 items-center justify-center rounded-full border border-input text-destructive"
                            >
                                <X className="size-4.5" />
                            </button>
                            <button
                                type="button"
                                aria-label={`Approve ${guest.name}`}
                                onClick={() => post(approve.url(guest))}
                                className="flex size-9 items-center justify-center rounded-full bg-success text-success-foreground"
                            >
                                <Check className="size-4.5" />
                            </button>
                        </div>
                    ))}
                    <GroupLabel>All guests</GroupLabel>
                </>
            )}
            {others.map((guest) => {
                const pill = replyPills[guest.rsvp_status];
                const line = guestLine(guest);

                return (
                    <button
                        key={guest.id}
                        type="button"
                        onClick={() => onSelect(guest.id)}
                        className="flex w-full items-center gap-3 border-b border-border px-4 py-3 text-left last:border-b-0"
                    >
                        <Avatar name={guest.name} />
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-semibold">
                                {guest.name}
                            </span>
                            <span
                                className={cn(
                                    'flex items-center gap-1 truncate text-xs',
                                    line.failed
                                        ? 'text-destructive'
                                        : 'text-subtle',
                                )}
                            >
                                {line.failed && (
                                    <AlertTriangle className="size-3.5" />
                                )}
                                {line.text}
                            </span>
                        </span>
                        <span
                            className={cn(
                                'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.75 text-[11px] font-semibold',
                                pill.className,
                            )}
                        >
                            <pill.icon className="size-3" />
                            {pill.label}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

function GroupLabel({ children }: { children: React.ReactNode }) {
    return (
        <div className="bg-background px-4 py-2 text-[11px] font-bold tracking-[0.06em] text-subtle uppercase">
            {children}
        </div>
    );
}

function Avatar({ name, accent = false }: { name: string; accent?: boolean }) {
    return (
        <span
            className={cn(
                'flex size-10 shrink-0 items-center justify-center rounded-full text-[13px] font-bold',
                accent
                    ? 'bg-accent text-accent-foreground'
                    : 'bg-foreground/6 text-muted-foreground',
            )}
        >
            {initials(name)}
        </span>
    );
}
