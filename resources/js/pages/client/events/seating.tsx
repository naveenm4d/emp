import { Head, router } from '@inertiajs/react';
import { Armchair, Plus, Sparkles, UserPlus } from 'lucide-react';
import { useMemo, useState } from 'react';

import { SeatingPreview } from '@/components/plans/feature-previews';
import { LockedEventPage } from '@/components/plans/locked-feature';
import { FloorPlan } from '@/components/seating/floor-plan';
import { GuestPicker } from '@/components/seating/guest-picker';
import { SeatLegend, TableCard } from '@/components/seating/table-card';
import { TableFormDialog } from '@/components/seating/table-form-dialog';
import { freeSeatsFor, TablePicker } from '@/components/seating/table-picker';
import { TableSheet } from '@/components/seating/table-sheet';
import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import { SegmentedControl } from '@/components/ui/segmented-control';
import EventLayout from '@/layouts/event-layout';
import { cn } from '@/lib/utils';
import { auto, replace, swap } from '@/routes/client/events/seating';
import { destroy as freeSeats } from '@/routes/client/guests/seats';
import { destroy as destroyTable } from '@/routes/client/tables';
import { store as assignSeat } from '@/routes/client/tables/seats';
import type {
    Event,
    EventTable,
    Option,
    Resource,
    SeatingGuest,
    SeatingSummary,
    VenueElement,
    VenueElementKindOption,
} from '@/types';

type Props = {
    event: Resource<Event>;
    locked?: undefined;
    tables: EventTable[];
    guests: SeatingGuest[];
    summary: SeatingSummary;
    tableShapes: Option[];
    venueElements: VenueElement[];
    venueElementKinds: VenueElementKindOption[];
};

/** Not in the client's plan: the controller sends only the event. */
type LockedProps = { event: Resource<Event>; locked: true };

/**
 * Who is being moved with the table picker: the whole party, the party
 * members still without a seat, or one person of the party.
 */
type Moving =
    | { kind: 'party'; guest: SeatingGuest }
    | { kind: 'rest'; guest: SeatingGuest }
    | { kind: 'member'; guest: SeatingGuest; member: number };

/** Which guest picker is open, and what picking a guest does. */
type Picking =
    | { kind: 'seat'; table: EventTable; seatNumber: number }
    | { kind: 'swap'; guest: SeatingGuest }
    | { kind: 'replace'; guest: SeatingGuest };

export default function Seating(props: Props | LockedProps) {
    if (props.locked) {
        return (
            <LockedEventPage
                event={props.event.data}
                title="Seating"
                feature="seating"
                preview={<SeatingPreview />}
            />
        );
    }

    return <SeatingPage {...props} />;
}

/**
 * The event's Seating tab: tables with coloured seats (by RSVP status),
 * guests without a seat, and the tools to seat, move, swap or replace them.
 * A guest's plus-ones and children always sit with them. The Map view is a
 * floor plan where tables and venue elements are dragged into place.
 */
function SeatingPage({
    event: { data: event },
    tables,
    guests,
    summary,
    tableShapes,
    venueElements,
    venueElementKinds,
}: Props) {
    const editable = event.state !== 'cancelled';
    const byId = useMemo(
        () => new Map(guests.map((guest) => [guest.id, guest])),
        [guests],
    );

    const [openTableId, setOpenTableId] = useState<string | null>(null);
    // The seat clicked on a table drawing, shown expanded in the table's panel.
    const [openSeat, setOpenSeat] = useState<number | null>(null);
    const [view, setView] = useState<'list' | 'map'>('list');
    const [tableForm, setTableForm] = useState<{
        open: boolean;
        table: EventTable | null;
    }>({ open: false, table: null });
    const [picking, setPicking] = useState<Picking | null>(null);
    const [moving, setMoving] = useState<Moving | null>(null);

    const openTable = tables.find((table) => table.id === openTableId) ?? null;
    const post = (url: string, data: Record<string, string | number> = {}) =>
        router.post(url, data, { preserveScroll: true });

    /** Asks before a party is split over tables. */
    const confirmSplit = (
        guest: SeatingGuest,
        table: EventTable,
        free: number,
    ) =>
        confirm(
            `Only ${free} of ${guest.name}'s party of ${guest.party_size} fit at ${table.name}. Split the party? The other ${guest.party_size - free} will need seats elsewhere.`,
        );

    /** Seats `moving` at the table's first free seat. */
    const moveTo = (table: EventTable, free: number) => {
        if (!moving) {
            return;
        }

        const { guest } = moving;
        const firstFree = freeSeatsFor(
            table,
            moving.kind === 'party' ? guest : null,
        )[0];

        if (!firstFree) {
            return;
        }

        const payload = { guest_id: guest.id, seat_number: firstFree.number };

        if (moving.kind === 'member') {
            post(assignSeat.url(table), {
                ...payload,
                mode: 'member',
                party_member: moving.member,
            });
        } else if (moving.kind === 'rest') {
            post(assignSeat.url(table), { ...payload, mode: 'rest' });
        } else if (free >= guest.party_size) {
            post(assignSeat.url(table), payload);
        } else if (confirmSplit(guest, table, free)) {
            post(assignSeat.url(table), { ...payload, mode: 'split' });
        }
    };

    const tablePicker = (() => {
        if (!moving) {
            return null;
        }

        const { guest } = moving;

        if (moving.kind === 'member') {
            const label =
                guest.seats.find((seat) => seat.party_member === moving.member)
                    ?.label ??
                guest.missing_members.find(
                    (person) => person.party_member === moving.member,
                )?.label ??
                guest.name;

            return {
                title: `Seat ${label} at…`,
                description: `Only this person moves; the rest of ${guest.name}'s party stays.`,
                need: 1,
                tables,
                freeAt: (table: EventTable) => freeSeatsFor(table).length,
            };
        }

        if (moving.kind === 'rest') {
            const need = guest.missing_members.length;

            return {
                title: `Seat the rest of ${guest.name}'s party at…`,
                description: `${need} still ${need === 1 ? 'needs a seat' : 'need seats'}. The others keep theirs.`,
                need,
                tables,
                freeAt: (table: EventTable) => freeSeatsFor(table).length,
            };
        }

        return {
            title: `Seat ${guest.name} at…`,
            description:
                guest.party_size > 1
                    ? `The party of ${guest.party_size} sits together; where it doesn't fit you can split it.`
                    : 'They take the first free seat.',
            need: guest.party_size,
            tables: tables.filter(
                (table) =>
                    !guest.seats.every((seat) => seat.table_id === table.id) ||
                    guest.seats.length === 0,
            ),
            freeAt: (table: EventTable) => freeSeatsFor(table, guest).length,
        };
    })();

    const seatable = guests.filter((guest) => guest.rsvp_status !== 'declined');

    const picker = (() => {
        if (!picking) {
            return null;
        }

        if (picking.kind === 'seat') {
            const free = freeSeatsFor(picking.table).length;

            const partlySeated = (guest: SeatingGuest) =>
                guest.seated > 0 && guest.missing_members.length > 0;

            return {
                title: `Seat ${picking.seatNumber} at ${picking.table.name}`,
                description: `${free} free ${free === 1 ? 'seat' : 'seats'}. A guest's party sits together unless you split it.`,
                guests: seatable,
                disabledReason: () => null,
                note: (guest: SeatingGuest) => {
                    if (partlySeated(guest)) {
                        const need = guest.missing_members.length;

                        return `${need} of the party ${need === 1 ? 'needs a seat' : 'need seats'}: seats them here`;
                    }

                    const fits = freeSeatsFor(picking.table, guest).length;

                    return fits < guest.party_size
                        ? `Party of ${guest.party_size} · only ${fits} fit here, the party will be split`
                        : null;
                },
                onPick: (guest: SeatingGuest) => {
                    const payload = {
                        guest_id: guest.id,
                        seat_number: picking.seatNumber,
                    };
                    const fits = freeSeatsFor(picking.table, guest).length;

                    if (partlySeated(guest)) {
                        post(assignSeat.url(picking.table), {
                            ...payload,
                            mode: 'rest',
                        });
                    } else if (fits >= guest.party_size) {
                        post(assignSeat.url(picking.table), payload);
                    } else if (confirmSplit(guest, picking.table, fits)) {
                        post(assignSeat.url(picking.table), {
                            ...payload,
                            mode: 'split',
                        });
                    }
                },
            };
        }

        const { guest } = picking;
        const others = (
            picking.kind === 'swap'
                ? guests.filter((other) => other.seated > 0)
                : seatable
        ).filter((other) => other.id !== guest.id);

        return {
            title:
                picking.kind === 'swap'
                    ? `Swap ${guest.name} with…`
                    : `Replace ${guest.name} with…`,
            description:
                picking.kind === 'swap'
                    ? 'The two parties change places.'
                    : `${guest.name}'s seats are freed and the guest you pick sits there.`,
            guests: others,
            disabledReason: () => null,
            note: () => null,
            onPick: (other: SeatingGuest) =>
                post((picking.kind === 'swap' ? swap : replace).url(event), {
                    guest_id: guest.id,
                    other_guest_id: other.id,
                }),
        };
    })();

    return (
        <EventLayout event={event}>
            <Head title={`Seating · ${event.title}`} />
            <PageHeader
                eyebrow={event.title}
                title="Seating"
                description={`${summary.confirmed_seated} of ${summary.confirmed_people} seated · ${tables.length} ${tables.length === 1 ? 'table' : 'tables'}`}
                actions={
                    <>
                        {tables.length > 0 && (
                            <SegmentedControl
                                label="Seating view"
                                className="w-32"
                                value={view}
                                onChange={setView}
                                items={[
                                    { value: 'list' as const, label: 'List' },
                                    { value: 'map' as const, label: 'Map' },
                                ]}
                            />
                        )}
                        {editable && (
                            <>
                                {summary.unseated.length > 0 &&
                                    tables.length > 0 && (
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                post(auto.url(event))
                                            }
                                        >
                                            <Sparkles />
                                            <span className="max-sm:sr-only">
                                                Auto-seat
                                            </span>
                                        </Button>
                                    )}
                                <Button
                                    onClick={() =>
                                        setTableForm({
                                            open: true,
                                            table: null,
                                        })
                                    }
                                >
                                    <Plus />
                                    <span className="max-sm:sr-only">
                                        Add table
                                    </span>
                                </Button>
                            </>
                        )}
                    </>
                }
            />

            <div
                className={cn(
                    'grid grid-cols-1 gap-4',
                    // The map takes the full width; unseated parties go under it.
                    view === 'list' && 'lg:grid-cols-[minmax(0,1fr)_340px]',
                )}
            >
                {tables.length === 0 ? (
                    <div className="rounded-xl bg-card shadow-card">
                        <EmptyState
                            icon={Armchair}
                            title="No tables yet"
                            description="Add tables (like Table 1 or A) with their number of seats, then seat your guests."
                        />
                    </div>
                ) : view === 'map' ? (
                    <div className="min-w-0">
                        <SeatLegend className="mb-3" />
                        <FloorPlan
                            className="-mx-3 md:mx-0 md:rounded-xl"
                            event={event}
                            tables={tables}
                            elements={venueElements}
                            kinds={venueElementKinds}
                            editable={editable}
                            onOpenTable={(table) => {
                                setOpenSeat(null);
                                setOpenTableId(table.id);
                            }}
                        />
                        {editable && (
                            <p className="mt-2 text-xs text-muted-foreground">
                                Drag tables and venue elements into place, drag
                                the floor to move around, and tap a table to see
                                its seats.
                            </p>
                        )}
                    </div>
                ) : (
                    <div>
                        <SeatLegend className="mb-4" />
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                            {tables.map((table) => (
                                <TableCard
                                    key={table.id}
                                    table={table}
                                    onOpen={(seatNumber) => {
                                        setOpenSeat(seatNumber ?? null);
                                        setOpenTableId(table.id);
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                )}

                {summary.unseated.length > 0 && (
                    <section className="flex h-fit flex-col gap-2.5 rounded-t-[20px] bg-card px-4 pt-3 pb-5 shadow-[0_-6px_20px_rgba(0,0,0,0.14)] max-md:-mx-3 md:rounded-xl md:shadow-card">
                        <div className="mx-auto h-1 w-10 rounded-full bg-foreground/14 md:hidden" />
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-base font-bold">
                                Unseated parties
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                {summary.unseated.length}{' '}
                                {summary.unseated.length === 1
                                    ? 'party'
                                    : 'parties'}{' '}
                                ·{' '}
                                {peopleCount(
                                    summary.unseated.reduce(
                                        (sum, item) => sum + item.missing,
                                        0,
                                    ),
                                )}
                            </span>
                        </div>
                        {summary.unseated.map((item) => {
                            const guest = byId.get(item.guest_id);

                            return (
                                <div
                                    key={item.guest_id}
                                    className="flex items-center gap-2.5 rounded-xl border border-input p-2.5"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate text-sm font-semibold">
                                            {item.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {item.missing === item.party_size
                                                ? peopleCount(item.party_size)
                                                : `needs ${item.missing} more ${item.missing === 1 ? 'seat' : 'seats'}`}
                                        </div>
                                    </div>
                                    {editable && guest && tables.length > 0 && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setMoving({
                                                    kind:
                                                        guest.seated > 0
                                                            ? 'rest'
                                                            : 'party',
                                                    guest,
                                                })
                                            }
                                            className="inline-flex h-7.5 items-center gap-1 rounded-full border border-input px-2.5 text-xs font-semibold hover:bg-raised"
                                        >
                                            <UserPlus className="size-3.5" />
                                            {guest.seated > 0
                                                ? 'Seat the rest'
                                                : 'Seat'}
                                        </button>
                                    )}
                                </div>
                            );
                        })}
                    </section>
                )}
            </div>

            <TableSheet
                table={openTable}
                initialSeat={openSeat}
                guests={byId}
                editable={editable}
                onClose={() => setOpenTableId(null)}
                onEdit={(table) => setTableForm({ open: true, table })}
                onDelete={(table) => {
                    setOpenTableId(null);
                    router.delete(destroyTable.url(table), {
                        preserveScroll: true,
                    });
                }}
                onSeatGuest={(table, seatNumber) =>
                    setPicking({ kind: 'seat', table, seatNumber })
                }
                onMove={(guest) => setMoving({ kind: 'party', guest })}
                onMoveMember={(guest, member) =>
                    setMoving({ kind: 'member', guest, member })
                }
                onSwap={(guest) => setPicking({ kind: 'swap', guest })}
                onReplace={(guest) => setPicking({ kind: 'replace', guest })}
                onFree={(guest) =>
                    router.delete(freeSeats.url(guest), {
                        preserveScroll: true,
                    })
                }
                onFreeMember={(guest, member) =>
                    router.delete(
                        freeSeats.url(guest, {
                            query: { party_member: member },
                        }),
                        { preserveScroll: true },
                    )
                }
            />

            <TableFormDialog
                // Re-mount per table so the form starts from its values.
                key={tableForm.table?.id ?? `new-${tables.length}`}
                open={tableForm.open}
                onOpenChange={(open) =>
                    setTableForm((form) => ({ ...form, open }))
                }
                event={event}
                table={tableForm.table}
                suggestedName={`Table ${tables.length + 1}`}
                shapes={tableShapes}
            />

            {picker && (
                <GuestPicker
                    open={picking !== null}
                    onOpenChange={(open) => !open && setPicking(null)}
                    title={picker.title}
                    description={picker.description}
                    guests={picker.guests}
                    disabledReason={picker.disabledReason}
                    note={picker.note}
                    onPick={picker.onPick}
                />
            )}

            {tablePicker && (
                <TablePicker
                    open={moving !== null}
                    onOpenChange={(open) => !open && setMoving(null)}
                    title={tablePicker.title}
                    description={tablePicker.description}
                    need={tablePicker.need}
                    tables={tablePicker.tables}
                    freeAt={tablePicker.freeAt}
                    onPick={moveTo}
                />
            )}
        </EventLayout>
    );
}

function peopleCount(count: number): string {
    return `${count} ${count === 1 ? 'person' : 'people'}`;
}
