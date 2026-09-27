import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeftRight,
    ChevronDown,
    Mail,
    MessageCircle,
    MoveRight,
    Pencil,
    Phone,
    Plus,
    Replace,
    Trash2,
    UserMinus,
    UserPlus,
    UserRound,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import { seatColors, seatTone } from '@/components/seating/seat-colors';
import type { SeatTone } from '@/components/seating/seat-colors';
import { TableToken } from '@/components/seating/table-token';
import { ConfirmBar, useConfirm } from '@/components/shared/confirm-bar';
import { StatusBadge } from '@/components/shared/status-badge';
import {
    Sheet,
    SheetBody,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { EventTable, Seat, SeatingGuest } from '@/types';

type SeatActions = {
    onMove: (guest: SeatingGuest) => void;
    onSwap: (guest: SeatingGuest) => void;
    onReplace: (guest: SeatingGuest) => void;
    onFree: (guest: SeatingGuest) => void;
    /** Seat or move one person of the party (split it). */
    onMoveMember: (guest: SeatingGuest, partyMember: number) => void;
    onFreeMember: (guest: SeatingGuest, partyMember: number) => void;
};

type TableSheetProps = SeatActions & {
    /** The open table (kept fresh from the page's props); null closes it. */
    table: EventTable | null;
    /** The seat to show expanded when the table opens (a seat clicked on the drawing). */
    initialSeat?: number | null;
    guests: Map<string, SeatingGuest>;
    editable: boolean;
    onClose: () => void;
    onEdit: (table: EventTable) => void;
    onDelete: (table: EventTable) => void;
    onSeatGuest: (table: EventTable, seatNumber: number) => void;
};

const shapeLabels: Record<EventTable['shape'], string> = {
    round: 'Round',
    oval: 'Oval',
    square: 'Square',
    rectangle: 'Rectangle',
};

/** Filled seat circles by who sits there (the empty one is outlined). */
const seatCircle: Record<SeatTone, string> = {
    confirmed: 'bg-success text-white',
    pending: 'bg-warning text-white',
    maybe: 'bg-info text-white',
    declined: 'bg-destructive text-white',
    empty: 'border-2 border-dashed border-input text-subtle',
};

/**
 * One table at a glance: the table with its ring of seats, the actions as
 * icons, and every seat. Tapping a taken seat opens the guest right under it;
 * an empty seat seats someone.
 */
export function TableSheet({
    table,
    initialSeat,
    guests,
    editable,
    onClose,
    onEdit,
    onDelete,
    onSeatGuest,
    ...actions
}: TableSheetProps) {
    // Keep the last table while the sheet animates closed.
    const [shown, setShown] = useState(table);
    const confirmation = useConfirm();
    const [openSeat, setOpenSeat] = useState<number | null>(null);

    useEffect(() => {
        if (table) {
            setShown(table);
        }
    }, [table]);

    // A newly opened table starts at the clicked seat, or collapsed.
    useEffect(() => {
        setOpenSeat(initialSeat ?? null);
    }, [table?.id, initialSeat]);

    // A question left open about another table goes away.
    const { cancel: cancelConfirmation } = confirmation;
    useEffect(() => cancelConfirmation(), [table?.id, cancelConfirmation]);

    const counts = (shown?.seats ?? []).reduce<Record<SeatTone, number>>(
        (all, seat) => {
            all[seat.guest_id ? seatTone(seat.status) : 'empty']++;

            return all;
        },
        { confirmed: 0, pending: 0, maybe: 0, declined: 0, empty: 0 },
    );
    const firstFree = shown?.seats.find((seat) => !seat.guest_id);

    return (
        <Sheet
            open={table !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <SheetContent className="bg-background">
                {shown && (
                    <SheetBody className="grid grid-cols-1 content-start gap-6 p-0 pb-8 *:min-w-0">
                        <div className="flex flex-col items-center gap-3 bg-linear-to-b from-card to-background px-4 pt-7 pb-1 text-center">
                            <TableToken table={shown} className="scale-90" />
                            <div className="max-w-full min-w-0">
                                <SheetTitle className="truncate text-xl font-bold">
                                    {shown.name}
                                </SheetTitle>
                                <SheetDescription className="mt-0.5 text-sm">
                                    {shapeLabels[shown.shape]} table ·{' '}
                                    {shown.seat_count} seats
                                </SheetDescription>
                            </div>
                            <ul className="flex flex-wrap justify-center gap-1.5">
                                {(Object.keys(counts) as SeatTone[])
                                    .filter((tone) => counts[tone] > 0)
                                    .map((tone) => (
                                        <li
                                            key={tone}
                                            className="inline-flex items-center gap-1.5 rounded-full bg-card px-2.5 py-1 text-xs font-semibold shadow-card"
                                        >
                                            <span
                                                className={cn(
                                                    'size-2 rounded-full',
                                                    seatColors[tone].bg,
                                                )}
                                            />
                                            {counts[tone]}{' '}
                                            {tone === 'empty'
                                                ? 'free'
                                                : seatColors[
                                                      tone
                                                  ].label.toLowerCase()}
                                        </li>
                                    ))}
                            </ul>
                        </div>

                        {editable && (
                            <div className="relative mx-4 grid grid-cols-3 gap-2 rounded-2xl">
                                <ConfirmBar
                                    request={confirmation.request}
                                    onCancel={confirmation.cancel}
                                />
                                <IconAction
                                    icon={UserPlus}
                                    label="Seat a guest"
                                    primary
                                    disabled={!firstFree}
                                    title={
                                        firstFree
                                            ? `Seat someone in seat ${firstFree.number}`
                                            : 'Every seat is taken'
                                    }
                                    onClick={() =>
                                        firstFree &&
                                        onSeatGuest(shown, firstFree.number)
                                    }
                                />
                                <IconAction
                                    icon={Pencil}
                                    label="Edit table"
                                    onClick={() => onEdit(shown)}
                                />
                                <IconAction
                                    icon={Trash2}
                                    label="Remove"
                                    danger
                                    onClick={() =>
                                        confirmation.ask({
                                            title: `Remove ${shown.name}? Everyone seated there loses their seat.`,
                                            confirmLabel: 'Remove',
                                            onConfirm: () => onDelete(shown),
                                        })
                                    }
                                />
                            </div>
                        )}

                        <section className="grid gap-2.5 px-4">
                            <h3 className="px-1 text-sm font-bold">Seats</h3>
                            <ol className="overflow-hidden rounded-2xl bg-card shadow-card">
                                {shown.seats.map((seat, index) => (
                                    <SeatRow
                                        key={seat.number}
                                        seat={seat}
                                        table={shown}
                                        guest={
                                            seat.guest_id
                                                ? guests.get(seat.guest_id)
                                                : undefined
                                        }
                                        previous={shown.seats[index - 1]}
                                        next={shown.seats[index + 1]}
                                        expanded={openSeat === seat.number}
                                        editable={editable}
                                        onToggle={() =>
                                            setOpenSeat((open) =>
                                                open === seat.number
                                                    ? null
                                                    : seat.number,
                                            )
                                        }
                                        onSeatGuest={() =>
                                            onSeatGuest(shown, seat.number)
                                        }
                                        {...actions}
                                    />
                                ))}
                            </ol>
                        </section>
                    </SheetBody>
                )}
            </SheetContent>
        </Sheet>
    );
}

/** One seat: its number in the seat's colour, who sits there, and their details when open. */
function SeatRow({
    seat,
    table,
    guest,
    previous,
    next,
    expanded,
    editable,
    onToggle,
    onSeatGuest,
    ...actions
}: SeatActions & {
    seat: Seat;
    table: EventTable;
    guest: SeatingGuest | undefined;
    previous: Seat | undefined;
    next: Seat | undefined;
    expanded: boolean;
    editable: boolean;
    onToggle: () => void;
    onSeatGuest: () => void;
}) {
    const tone: SeatTone = guest ? seatTone(seat.status) : 'empty';
    const open = !!guest && expanded;
    // Tree lines join a guest to the party members in the rows right below.
    const joinsAbove = !!guest && previous?.guest_id === seat.guest_id;
    const joinsBelow =
        !!guest &&
        !open &&
        next?.guest_id === seat.guest_id &&
        (next.party_member ?? 0) > 0;
    const clickable = !!guest || editable;

    return (
        <li className="border-b border-border last:border-b-0">
            <div
                className={cn(
                    'relative flex items-center gap-3 px-3.5 py-2.5',
                    clickable && 'cursor-pointer hover:bg-raised',
                    open && 'bg-raised',
                )}
                role={clickable ? 'button' : undefined}
                tabIndex={clickable ? 0 : undefined}
                aria-expanded={guest ? open : undefined}
                onClick={() => (guest ? onToggle() : editable && onSeatGuest())}
                onKeyDown={(e) => {
                    if (clickable && (e.key === 'Enter' || e.key === ' ')) {
                        e.preventDefault();
                        e.currentTarget.click();
                    }
                }}
            >
                <span
                    className={cn(
                        'flex size-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold tabular-nums',
                        seatCircle[tone],
                    )}
                >
                    {seat.number}
                </span>
                {guest && seat.party_member === 0 && joinsBelow && <Trunk />}
                {guest && (seat.party_member ?? 0) > 0 && (
                    <Branch up={joinsAbove} down={joinsBelow} />
                )}
                <p
                    className={cn(
                        'min-w-0 flex-1 truncate text-sm',
                        guest ? 'font-semibold' : 'text-subtle',
                    )}
                >
                    {guest ? seat.label : 'Empty seat'}
                </p>
                {guest ? (
                    <>
                        {seat.party_member === 0 && (
                            <StatusBadge status={guest.rsvp_status} />
                        )}
                        <ChevronDown
                            className={cn(
                                'size-4 shrink-0 text-muted-foreground transition-transform',
                                open && 'rotate-180',
                            )}
                        />
                    </>
                ) : (
                    editable && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-foreground/6 px-2.5 py-1 text-[11px] font-semibold text-muted-foreground">
                            <Plus className="size-3" /> Seat
                        </span>
                    )
                )}
            </div>
            {open && guest && (
                <SeatDetails
                    seat={seat}
                    guest={guest}
                    table={table}
                    editable={editable}
                    {...actions}
                />
            )}
        </li>
    );
}

/** The guest behind a seat: their party and where each sits, contact, dietary needs and the seat actions. */
function SeatDetails({
    seat,
    guest,
    table,
    editable,
    onMove,
    onSwap,
    onReplace,
    onFree,
    onMoveMember,
    onFreeMember,
}: SeatActions & {
    seat: Seat;
    guest: SeatingGuest;
    table: EventTable;
    editable: boolean;
}) {
    const confirmation = useConfirm();
    const member = seat.party_member ?? 0;
    const inParty = guest.party_size > 1;
    const digits = guest.phone?.replace(/\D/g, '');
    // The whole party, the guest first, wherever each person sits (a party
    // can be split over tables), including who has no seat yet.
    const party = [
        ...guest.seats.map((other) => ({
            member: other.party_member,
            label: other.label,
            where:
                other.table_id === table.id
                    ? `Seat ${other.seat_number}`
                    : `${other.table_name} · seat ${other.seat_number}`,
            here: other.table_id === table.id,
        })),
        ...guest.missing_members.map((other) => ({
            member: other.party_member,
            label: other.label,
            where: null,
            here: false,
        })),
    ].sort((a, b) => a.member - b.member);

    return (
        <div className="grid gap-3 bg-raised px-3.5 pt-1 pb-4 motion-safe:animate-fade-in">
            {seat.party_member !== 0 && (
                <p className="text-xs text-muted-foreground">
                    Comes with {guest.name}
                </p>
            )}

            {inParty && (
                <div className="overflow-hidden rounded-xl bg-card shadow-card">
                    <p className="border-b border-border px-3 py-2 text-xs font-semibold text-muted-foreground">
                        Party of {guest.party_size}
                    </p>
                    <ul>
                        {party.map((person) => (
                            <li
                                key={person.member}
                                className={cn(
                                    'flex items-center justify-between gap-3 border-b border-border px-3 py-2 text-sm last:border-b-0',
                                    person.member === member && 'font-semibold',
                                )}
                            >
                                <span className="min-w-0 truncate">
                                    {person.label}
                                </span>
                                {person.where ? (
                                    <span
                                        className={cn(
                                            'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                            person.here
                                                ? 'bg-foreground/6 text-muted-foreground'
                                                : 'bg-info-muted text-info',
                                        )}
                                    >
                                        {person.where}
                                    </span>
                                ) : editable ? (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onMoveMember(guest, person.member)
                                        }
                                        className="shrink-0 rounded-full bg-warning-muted px-2 py-0.5 text-[11px] font-semibold text-warning hover:opacity-80"
                                    >
                                        Not seated · Seat
                                    </button>
                                ) : (
                                    <span className="shrink-0 rounded-full bg-warning-muted px-2 py-0.5 text-[11px] font-semibold text-warning">
                                        Not seated
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {(guest.phone || guest.email) && (
                <div className="flex items-center gap-3 rounded-xl bg-card px-3 py-2 shadow-card">
                    <div className="min-w-0 flex-1 text-sm">
                        {guest.phone && (
                            <p className="truncate font-semibold">
                                {guest.phone}
                            </p>
                        )}
                        {guest.email && (
                            <p className="truncate text-xs text-muted-foreground">
                                {guest.email}
                            </p>
                        )}
                    </div>
                    <div className="flex shrink-0 gap-1.5">
                        {digits && (
                            <ContactLink
                                href={`https://wa.me/${digits}`}
                                label="WhatsApp"
                                icon={MessageCircle}
                                className="bg-success-muted text-success"
                            />
                        )}
                        {guest.phone && (
                            <ContactLink
                                href={`tel:${guest.phone}`}
                                label="Call"
                                icon={Phone}
                            />
                        )}
                        {guest.email && (
                            <ContactLink
                                href={`mailto:${guest.email}`}
                                label="Email"
                                icon={Mail}
                            />
                        )}
                    </div>
                </div>
            )}

            {(guest.dietary_restrictions.length > 0 || guest.dietary_notes) && (
                <div className="flex flex-wrap items-center gap-1.5">
                    {guest.dietary_restrictions.map((item) => (
                        <span
                            key={item}
                            className="rounded-full bg-card px-2.5 py-0.5 text-xs font-semibold shadow-card"
                        >
                            {headline(item)}
                        </span>
                    ))}
                    {guest.dietary_notes && (
                        <p className="w-full text-xs whitespace-pre-line text-muted-foreground">
                            {guest.dietary_notes}
                        </p>
                    )}
                </div>
            )}

            {editable && (
                <div className="relative grid grid-cols-4 gap-2 rounded-2xl pt-1">
                    <ConfirmBar
                        request={confirmation.request}
                        onCancel={confirmation.cancel}
                    />
                    <IconAction
                        icon={MoveRight}
                        label={inParty ? 'Move party' : 'Move'}
                        onClick={() => onMove(guest)}
                    />
                    {inParty && (
                        <IconAction
                            icon={UserRound}
                            label={
                                member === 0
                                    ? 'Move only them'
                                    : 'Move this one'
                            }
                            onClick={() => onMoveMember(guest, member)}
                        />
                    )}
                    <IconAction
                        icon={ArrowLeftRight}
                        label="Swap"
                        onClick={() => onSwap(guest)}
                    />
                    <IconAction
                        icon={Replace}
                        label="Replace"
                        onClick={() => onReplace(guest)}
                    />
                    {inParty && (
                        <IconAction
                            icon={UserMinus}
                            label="Free this seat"
                            danger
                            onClick={() =>
                                confirmation.ask({
                                    title: `Free seat ${seat.number}? ${seat.label} will need a new seat.`,
                                    confirmLabel: 'Free',
                                    onConfirm: () =>
                                        onFreeMember(guest, member),
                                })
                            }
                        />
                    )}
                    <IconAction
                        icon={UserMinus}
                        label={inParty ? 'Free party' : 'Free seat'}
                        danger
                        onClick={() =>
                            confirmation.ask({
                                title: inParty
                                    ? `Free all ${guest.party_size} seats of ${guest.name}’s party?`
                                    : `Free ${guest.name}’s seat?`,
                                confirmLabel: 'Free',
                                onConfirm: () => onFree(guest),
                            })
                        }
                    />
                </div>
            )}
        </div>
    );
}

/** A round icon button with its label under it (like the guest panel's actions). */
function IconAction({
    icon: Icon,
    label,
    title,
    primary,
    danger,
    disabled,
    onClick,
}: {
    icon: LucideIcon;
    label: string;
    title?: string;
    primary?: boolean;
    danger?: boolean;
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            title={title ?? label}
            disabled={disabled}
            onClick={onClick}
            className="group flex min-w-0 flex-col items-center gap-1.5 outline-none disabled:opacity-40 focus-visible:[&>span:first-child]:ring-3 focus-visible:[&>span:first-child]:ring-ring/50"
        >
            <span
                className={cn(
                    'flex size-11 items-center justify-center rounded-full transition-colors',
                    primary
                        ? 'bg-strong text-strong-foreground'
                        : danger
                          ? 'bg-destructive-muted text-destructive'
                          : 'bg-card text-foreground shadow-card group-hover:bg-raised',
                )}
            >
                <Icon className="size-5" strokeWidth={1.75} />
            </span>
            <span className="max-w-full truncate text-[11px] font-semibold text-muted-foreground">
                {label}
            </span>
        </button>
    );
}

function ContactLink({
    href,
    label,
    icon: Icon,
    className = 'bg-foreground/6 text-foreground',
}: {
    href: string;
    label: string;
    icon: LucideIcon;
    className?: string;
}) {
    return (
        <a
            href={href}
            target={href.startsWith('http') ? '_blank' : undefined}
            rel="noreferrer"
            aria-label={label}
            title={label}
            className={cn(
                'flex size-8 items-center justify-center rounded-full transition-opacity hover:opacity-80',
                className,
            )}
        >
            <Icon className="size-4" strokeWidth={2} />
        </a>
    );
}

/**
 * Where the tree lines sit: the label column (px-3.5 + the seat circle and
 * its gap), plus a small inset.
 */
const TREE_LINE_LEFT = 'left-[60px]';

/** The line going down from a guest to their party members in the rows below. */
function Trunk() {
    return (
        <span
            aria-hidden
            className={cn(
                'absolute top-[calc(50%+0.7rem)] bottom-0 w-px bg-border',
                TREE_LINE_LEFT,
            )}
        />
    );
}

/**
 * A party member's branch: ├ when more of the party follows, └ for the last.
 * Without the host right above (the party wraps round the table), only a
 * short hook shows it belongs to someone.
 */
function Branch({ up, down }: { up: boolean; down: boolean }) {
    return (
        <span
            aria-hidden
            className="relative -my-2.5 -mr-1 w-4 shrink-0 self-stretch"
        >
            <span
                className={cn(
                    'absolute left-1.5 w-2.5 rounded-bl-md border-b border-l border-border',
                    up ? 'top-0 h-1/2' : 'top-[calc(50%-0.4rem)] h-[0.4rem]',
                )}
            />
            {down && (
                <span className="absolute top-1/2 bottom-0 left-1.5 w-px bg-border" />
            )}
        </span>
    );
}

/** gluten_free → Gluten free */
function headline(value: string): string {
    const words = value.replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}
