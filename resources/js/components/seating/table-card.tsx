import type { MouseEvent } from 'react';

import { seatColors, seatTone } from '@/components/seating/seat-colors';
import type { SeatTone } from '@/components/seating/seat-colors';
import { cn } from '@/lib/utils';
import type { EventTable, TableShape } from '@/types';

/** Radius of a seat dot, big enough for its number. */
const SEAT_RADIUS = 10;
/** Room each seat takes along the table's edge. */
const SEAT_PITCH = SEAT_RADIUS * 2 + 5;
/** Gap between the table's edge and the seats. */
const GAP = 5;

type Layout = {
    width: number;
    height: number;
    seats: { x: number; y: number }[];
    table:
        | { kind: 'ellipse'; rx: number; ry: number }
        | { kind: 'rect'; w: number; h: number };
};

/**
 * Where the seats go around a table of the given shape, seat 1 at the top
 * and the rest clockwise. The drawing grows with the number of seats so
 * the dots (and their numbers) keep their size.
 */
export function seatLayout(shape: TableShape, count: number): Layout {
    const margin = SEAT_RADIUS + 3;

    if (shape === 'round' || shape === 'oval') {
        const squash = shape === 'oval' ? 0.6 : 1;
        // Ellipse perimeter ≈ π(a + b) for the ring of seats.
        // Seats are spaced by angle, so they sit closest at the narrow ends:
        // size the ring so even there each seat has room.
        const ringX = Math.max(
            52,
            (count * SEAT_PITCH) / (2 * Math.PI * squash),
        );
        const ringY = ringX * squash;
        const cx = ringX + margin;
        const cy = ringY + margin;

        return {
            width: cx * 2,
            height: cy * 2,
            seats: Array.from({ length: count }, (_, index) => {
                const angle = (index / count) * Math.PI * 2 - Math.PI / 2;

                return {
                    x: cx + ringX * Math.cos(angle),
                    y: cy + ringY * Math.sin(angle),
                };
            }),
            table: {
                kind: 'ellipse',
                rx: ringX - SEAT_RADIUS - GAP,
                ry: ringY - SEAT_RADIUS - GAP,
            },
        };
    }

    // Square and rectangle: each side gets its share of the seats, spaced
    // evenly with half a gap at the corners (so no seat sits on a corner).
    const ratio = shape === 'rectangle' ? 2 : 1;
    const pitch = SEAT_PITCH + 5;
    let ringH = Math.max(
        shape === 'rectangle' ? 56 : 90,
        (count * pitch) / (2 * (ratio + 1)),
    );
    let perSide = shareSeats(count, [
        ringH * ratio,
        ringH,
        ringH * ratio,
        ringH,
    ]);

    // Rounding can crowd a side; grow the table until every side has room.
    while (
        perSide.some(
            (seats, side) =>
                seats > 0 &&
                (ringH * (side % 2 === 0 ? ratio : 1)) / seats < pitch,
        )
    ) {
        ringH *= 1.05;
        perSide = shareSeats(count, [
            ringH * ratio,
            ringH,
            ringH * ratio,
            ringH,
        ]);
    }

    const ringW = ringH * ratio;
    const left = margin;
    const top = margin;
    const seats: { x: number; y: number }[] = [];

    perSide.forEach((onSide, side) => {
        const length = side % 2 === 0 ? ringW : ringH;

        for (let index = 0; index < onSide; index++) {
            const along = (length * (index + 0.5)) / onSide;
            seats.push(
                [
                    { x: left + along, y: top }, // top, left to right
                    { x: left + ringW, y: top + along }, // right, top to bottom
                    { x: left + ringW - along, y: top + ringH }, // bottom, right to left
                    { x: left, y: top + ringH - along }, // left, bottom to top
                ][side],
            );
        }
    });

    return {
        width: ringW + margin * 2,
        height: ringH + margin * 2,
        seats,
        table: {
            kind: 'rect',
            w: ringW - 2 * (SEAT_RADIUS + GAP),
            h: ringH - 2 * (SEAT_RADIUS + GAP),
        },
    };
}

/** Splits the seats over the sides in proportion to their length (largest remainder). */
function shareSeats(count: number, sides: number[]): number[] {
    const total = sides.reduce((sum, side) => sum + side, 0);
    const ideal = sides.map((side) => (count * side) / total);
    const shares = ideal.map(Math.floor);
    const byRemainder = ideal
        .map((value, side) => ({ side, rest: value - Math.floor(value) }))
        .sort((a, b) => b.rest - a.rest);

    for (
        let index = 0;
        shares.reduce((sum, n) => sum + n, 0) < count;
        index++
    ) {
        shares[byRemainder[index % sides.length].side]++;
    }

    return shares;
}

/**
 * A table seen from above, in its shape, with numbered seats coloured by the
 * status of whoever sits there. Clicking the table opens its details;
 * clicking a seat opens them at that seat.
 */
export function TableCard({
    table,
    onOpen,
}: {
    table: EventTable;
    onOpen: (seatNumber?: number) => void;
}) {
    const taken = table.seats.filter((seat) => seat.guest_id).length;
    const layout = seatLayout(table.shape, table.seat_count);
    const cx = layout.width / 2;
    const cy = layout.height / 2;
    const label =
        table.name.length > 10 ? `${table.name.slice(0, 9)}…` : table.name;

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={() => onOpen()}
            onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onOpen();
                }
            }}
            className="group flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/40 hover:bg-muted/40 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <svg
                viewBox={`0 0 ${layout.width} ${layout.height}`}
                className="h-auto w-full"
                style={{
                    maxWidth: Math.max(150, Math.min(layout.width * 1.1, 260)),
                }}
                role="img"
                aria-label={`${table.name}: ${taken} of ${table.seat_count} seats taken`}
            >
                {layout.table.kind === 'ellipse' ? (
                    <ellipse
                        cx={cx}
                        cy={cy}
                        rx={layout.table.rx}
                        ry={layout.table.ry}
                        className="fill-background stroke-border"
                        strokeWidth={1.5}
                    />
                ) : (
                    <rect
                        x={cx - layout.table.w / 2}
                        y={cy - layout.table.h / 2}
                        width={layout.table.w}
                        height={layout.table.h}
                        rx={6}
                        className="fill-background stroke-border"
                        strokeWidth={1.5}
                    />
                )}
                <text
                    x={cx}
                    y={cy}
                    textAnchor="middle"
                    dominantBaseline="central"
                    className="fill-foreground text-[13px] font-semibold"
                >
                    {label}
                </text>
                {table.seats.map((seat, index) => {
                    const point = layout.seats[index];
                    const tone = seatTone(seat.status);

                    return (
                        <g
                            key={seat.number}
                            className="cursor-pointer"
                            onClick={(e: MouseEvent) => {
                                e.stopPropagation();
                                onOpen(seat.number);
                            }}
                        >
                            <title>{`Seat ${seat.number}: ${seat.label ?? 'empty'}`}</title>
                            <circle
                                cx={point.x}
                                cy={point.y}
                                r={SEAT_RADIUS}
                                strokeWidth={1}
                                className={cn(
                                    seatColors[tone].fill,
                                    'transition-opacity hover:opacity-80',
                                )}
                            />
                            <text
                                x={point.x}
                                y={point.y}
                                textAnchor="middle"
                                dominantBaseline="central"
                                className={cn(
                                    'pointer-events-none text-[9.5px] font-semibold tabular-nums',
                                    tone === 'empty'
                                        ? 'fill-muted-foreground'
                                        : 'fill-white',
                                )}
                            >
                                {seat.number}
                            </text>
                        </g>
                    );
                })}
            </svg>
            <div className="text-center">
                <p className="font-medium">{table.name}</p>
                <p className="text-xs text-muted-foreground">
                    {taken} / {table.seat_count} seats taken
                </p>
            </div>
        </div>
    );
}

/** What each seat colour means. */
export function SeatLegend({ className }: { className?: string }) {
    const tones: SeatTone[] = [
        'confirmed',
        'pending',
        'maybe',
        'declined',
        'empty',
    ];

    return (
        <ul
            className={cn(
                'flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-muted-foreground',
                className,
            )}
        >
            {tones.map((tone) => (
                <li key={tone} className="flex items-center gap-1.5">
                    <span
                        className={cn(
                            'size-2.5 rounded-full',
                            seatColors[tone].bg,
                        )}
                    />
                    {seatColors[tone].label}
                </li>
            ))}
        </ul>
    );
}
