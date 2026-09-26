import {
    ArrowLeftRight,
    ChevronDown,
    Mail,
    MoreHorizontal,
    MoveRight,
    Pencil,
    Phone,
    Plus,
    Replace,
    Trash2,
    UserMinus,
    UserRound,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { seatColors, seatTone } from '@/components/seating/seat-colors';
import type { SeatTone } from '@/components/seating/seat-colors';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetBody,
    SheetContent,
    SheetDescription,
    SheetHeader,
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

/**
 * Everything about one table: its seats and who sits where. Clicking a taken
 * seat opens the guest's details right under it; an empty seat seats someone.
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

    const counts = (shown?.seats ?? []).reduce<Record<SeatTone, number>>(
        (all, seat) => {
            all[seatTone(seat.status)]++;

            return all;
        },
        { confirmed: 0, pending: 0, maybe: 0, declined: 0, empty: 0 },
    );

    return (
        <Sheet
            open={table !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <SheetContent>
                {shown && (
                    <>
                        <SheetHeader>
                            <SheetTitle className="text-lg">
                                {shown.name}
                            </SheetTitle>
                            <SheetDescription render={<div />}>
                                <p className="mb-1.5">
                                    {shapeLabels[shown.shape]} ·{' '}
                                    {shown.seat_count} seats
                                </p>
                                <ul className="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                    {(Object.keys(counts) as SeatTone[]).map(
                                        (tone) => (
                                            <li
                                                key={tone}
                                                className="flex items-center gap-1.5"
                                            >
                                                <Dot tone={tone} small />
                                                {counts[tone]}{' '}
                                                {tone === 'empty'
                                                    ? 'free'
                                                    : seatColors[
                                                          tone
                                                      ].label.toLowerCase()}
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </SheetDescription>
                            {editable && (
                                <div className="mt-2 flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => onEdit(shown)}
                                    >
                                        <Pencil /> Edit
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        className="text-destructive"
                                        onClick={() => onDelete(shown)}
                                    >
                                        <Trash2 /> Remove table
                                    </Button>
                                </div>
                            )}
                        </SheetHeader>
                        <SheetBody className="p-0">
                            <ol className="divide-y">
                                {shown.seats.map((seat, index) => {
                                    const guest = seat.guest_id
                                        ? guests.get(seat.guest_id)
                                        : undefined;
                                    const expanded =
                                        !!guest && openSeat === seat.number;
                                    // Tree lines join a guest to the party members in the rows right below.
                                    const previous = shown.seats[index - 1];
                                    const next = shown.seats[index + 1];
                                    const joinsAbove =
                                        !!guest &&
                                        previous?.guest_id === seat.guest_id;
                                    const joinsBelow =
                                        !!guest &&
                                        !expanded &&
                                        next?.guest_id === seat.guest_id &&
                                        (next.party_member ?? 0) > 0;

                                    return (
                                        <li key={seat.number}>
                                            <div
                                                className={cn(
                                                    'relative flex items-center gap-3 px-4 py-2.5',
                                                    (guest || editable) &&
                                                        'cursor-pointer hover:bg-muted/50',
                                                    expanded && 'bg-muted/50',
                                                )}
                                                role="button"
                                                tabIndex={0}
                                                aria-expanded={
                                                    guest ? expanded : undefined
                                                }
                                                onClick={() => {
                                                    if (guest) {
                                                        setOpenSeat(
                                                            expanded
                                                                ? null
                                                                : seat.number,
                                                        );
                                                    } else if (editable) {
                                                        onSeatGuest(
                                                            shown,
                                                            seat.number,
                                                        );
                                                    }
                                                }}
                                                onKeyDown={(e) => {
                                                    if (
                                                        e.key === 'Enter' ||
                                                        e.key === ' '
                                                    ) {
                                                        e.preventDefault();
                                                        e.currentTarget.click();
                                                    }
                                                }}
                                            >
                                                <span className="w-6 text-right text-xs text-muted-foreground tabular-nums">
                                                    {seat.number}
                                                </span>
                                                <Dot
                                                    tone={seatTone(seat.status)}
                                                />
                                                {guest &&
                                                    seat.party_member === 0 &&
                                                    joinsBelow && <Trunk />}
                                                {guest &&
                                                    (seat.party_member ?? 0) >
                                                        0 && (
                                                        <Branch
                                                            up={joinsAbove}
                                                            down={joinsBelow}
                                                        />
                                                    )}
                                                <p
                                                    className={cn(
                                                        'min-w-0 flex-1 truncate',
                                                        guest
                                                            ? 'font-medium'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {guest
                                                        ? seat.label
                                                        : 'Empty'}
                                                </p>
                                                {guest &&
                                                    seat.party_member === 0 && (
                                                        <StatusBadge
                                                            status={
                                                                guest.rsvp_status
                                                            }
                                                        />
                                                    )}
                                                {guest ? (
                                                    <>
                                                        {editable && (
                                                            <SeatMenu
                                                                guest={guest}
                                                                seat={seat}
                                                                {...actions}
                                                            />
                                                        )}
                                                        <ChevronDown
                                                            className={cn(
                                                                'size-4 shrink-0 text-muted-foreground transition-transform',
                                                                expanded &&
                                                                    'rotate-180',
                                                            )}
                                                        />
                                                    </>
                                                ) : (
                                                    editable && (
                                                        <Plus
                                                            aria-label={`Seat a guest in seat ${seat.number}`}
                                                            className="size-4 text-muted-foreground"
                                                        />
                                                    )
                                                )}
                                            </div>
                                            {expanded && guest && (
                                                <SeatDetails
                                                    seat={seat}
                                                    guest={guest}
                                                    table={shown}
                                                    editable={editable}
                                                    {...actions}
                                                />
                                            )}
                                        </li>
                                    );
                                })}
                            </ol>
                        </SheetBody>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}

/** The guest behind a seat: status, the whole party and where each sits, contact and dietary needs. */
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
    const tone = seatTone(seat.status);
    const member = seat.party_member ?? 0;
    // The whole party as a tree, the guest first, wherever each person sits
    // (a party can be split over tables), including who has no seat yet.
    const party = [
        ...guest.seats.map((other) => ({
            member: other.party_member,
            label: other.label,
            where:
                other.table_id === table.id
                    ? `seat ${other.seat_number}`
                    : `${other.table_name}, seat ${other.seat_number}`,
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
        <div className="px-4 pb-3">
            <div
                className={cn(
                    'space-y-3 rounded-lg border border-l-4 bg-background p-3',
                    {
                        confirmed: 'border-l-emerald-500',
                        pending: 'border-l-amber-400',
                        maybe: 'border-l-sky-500',
                        declined: 'border-l-red-500',
                        empty: 'border-l-border',
                    }[tone],
                )}
            >
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="font-medium">{seat.label}</p>
                        {seat.party_member !== 0 && (
                            <p className="text-xs text-muted-foreground">
                                Guest of {guest.name}
                            </p>
                        )}
                    </div>
                    <StatusBadge status={guest.rsvp_status} />
                </div>

                <Detail label={`Party of ${guest.party_size}`}>
                    <ul className="space-y-0.5">
                        {party.map((person, index) => (
                            <li
                                key={person.member}
                                className={cn(
                                    'flex flex-wrap items-center gap-x-1',
                                    person.member === member && 'font-medium',
                                    !person.here &&
                                        person.member !== member &&
                                        'text-muted-foreground',
                                )}
                            >
                                {person.member > 0 && (
                                    <span
                                        aria-hidden
                                        className="font-mono text-muted-foreground"
                                    >
                                        {index === party.length - 1
                                            ? '└─'
                                            : '├─'}
                                    </span>
                                )}
                                {person.label}
                                {person.where ? (
                                    <span className="text-muted-foreground">
                                        · {person.where}
                                    </span>
                                ) : (
                                    <>
                                        <span className="text-amber-700 dark:text-amber-400">
                                            · not seated
                                        </span>
                                        {editable && (
                                            <button
                                                type="button"
                                                className="text-xs font-medium text-primary hover:underline"
                                                onClick={() =>
                                                    onMoveMember(
                                                        guest,
                                                        person.member,
                                                    )
                                                }
                                            >
                                                Seat…
                                            </button>
                                        )}
                                    </>
                                )}
                            </li>
                        ))}
                    </ul>
                </Detail>

                {(guest.phone || guest.email) && (
                    <Detail label="Contact">
                        <div className="flex flex-wrap gap-x-4 gap-y-1">
                            {guest.phone && (
                                <a
                                    href={`tel:${guest.phone}`}
                                    className="inline-flex items-center gap-1.5 hover:underline"
                                >
                                    <Phone className="size-3.5" />
                                    {guest.phone}
                                </a>
                            )}
                            {guest.email && (
                                <a
                                    href={`mailto:${guest.email}`}
                                    className="inline-flex min-w-0 items-center gap-1.5 break-all hover:underline"
                                >
                                    <Mail className="size-3.5 shrink-0" />
                                    {guest.email}
                                </a>
                            )}
                        </div>
                    </Detail>
                )}

                {(guest.dietary_restrictions.length > 0 ||
                    guest.dietary_notes) && (
                    <Detail label="Dietary">
                        {guest.dietary_restrictions.length > 0 && (
                            <p>
                                {guest.dietary_restrictions
                                    .map((value) => value.replace(/_/g, ' '))
                                    .join(', ')}
                            </p>
                        )}
                        {guest.dietary_notes && (
                            <p className="whitespace-pre-line text-muted-foreground">
                                {guest.dietary_notes}
                            </p>
                        )}
                    </Detail>
                )}

                {editable && (
                    <div className="flex flex-wrap gap-1.5 border-t pt-3">
                        {guest.party_size > 1 && (
                            <>
                                <Button
                                    size="xs"
                                    variant="outline"
                                    onClick={() => onMoveMember(guest, member)}
                                >
                                    <UserRound /> Move{' '}
                                    {member === 0
                                        ? `only ${guest.name}`
                                        : 'this person'}
                                </Button>
                                <Button
                                    size="xs"
                                    variant="ghost"
                                    className="text-destructive"
                                    onClick={() => onFreeMember(guest, member)}
                                >
                                    <UserMinus /> Free this seat
                                </Button>
                            </>
                        )}
                        <Button
                            size="xs"
                            variant="outline"
                            onClick={() => onMove(guest)}
                        >
                            <MoveRight />{' '}
                            {guest.party_size > 1 ? 'Move party' : 'Move'}
                        </Button>
                        <Button
                            size="xs"
                            variant="outline"
                            onClick={() => onSwap(guest)}
                        >
                            <ArrowLeftRight /> Swap
                        </Button>
                        <Button
                            size="xs"
                            variant="outline"
                            onClick={() => onReplace(guest)}
                        >
                            <Replace /> Replace
                        </Button>
                        <Button
                            size="xs"
                            variant="ghost"
                            className="text-destructive"
                            onClick={() => onFree(guest)}
                        >
                            <UserMinus /> Free{' '}
                            {guest.party_size > 1 ? 'party' : 'seat'}
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * Where the tree lines sit: the label column (px-4 + seat number + dot and
 * their gaps), plus a small inset.
 */
const TREE_LINE_LEFT = 'left-[82px]';

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

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="text-sm">
            <p className="mb-0.5 text-xs text-muted-foreground">{label}</p>
            {children}
        </div>
    );
}

function Dot({ tone, small }: { tone: SeatTone; small?: boolean }) {
    return (
        <span
            className={cn(
                'shrink-0 rounded-full',
                small ? 'size-2' : 'size-3',
                seatColors[tone].bg,
            )}
        />
    );
}

function SeatMenu({
    guest,
    seat,
    onMove,
    onSwap,
    onReplace,
    onFree,
    onMoveMember,
    onFreeMember,
}: SeatActions & { guest: SeatingGuest; seat: Seat }) {
    const member = seat.party_member ?? 0;
    const party = guest.party_size > 1;

    return (
        // Keep clicks in the menu from toggling the seat's details.
        <div
            onClick={(e) => e.stopPropagation()}
            onKeyDown={(e) => e.stopPropagation()}
        >
            <DropdownMenu>
                <DropdownMenuTrigger
                    aria-label={`Seat actions for ${seat.label ?? guest.name}`}
                    className="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none data-popup-open:bg-muted"
                >
                    <MoreHorizontal className="size-4" />
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    {party && (
                        <>
                            <DropdownMenuItem
                                onClick={() => onMoveMember(guest, member)}
                            >
                                <UserRound />
                                {member === 0
                                    ? `Move only ${guest.name}…`
                                    : 'Move this person…'}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => onFreeMember(guest, member)}
                            >
                                <UserMinus /> Free this seat
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                        </>
                    )}
                    <DropdownMenuItem onClick={() => onMove(guest)}>
                        <MoveRight />
                        {party ? 'Move whole party…' : 'Move to another table'}
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => onSwap(guest)}>
                        <ArrowLeftRight /> Swap with…
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => onReplace(guest)}>
                        <Replace /> Replace with…
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onClick={() => onFree(guest)}
                    >
                        <UserMinus /> Free {party ? 'whole party' : 'seat'}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
