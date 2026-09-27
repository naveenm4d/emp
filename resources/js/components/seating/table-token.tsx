import type { SeatTone } from '@/components/seating/seat-colors';
import { seatColors, seatTone } from '@/components/seating/seat-colors';
import { cn } from '@/lib/utils';
import type { EventTable, TableShape } from '@/types';

/** A table's footprint on the floor plan, in canvas units. */
export function tableTokenSize(shape: TableShape): {
    width: number;
    height: number;
} {
    switch (shape) {
        case 'oval':
            return { width: 190, height: 130 };
        case 'square':
            return { width: 140, height: 140 };
        case 'rectangle':
            return { width: 200, height: 120 };
        default:
            return { width: 150, height: 150 };
    }
}

/** Ring segments, in the order they're drawn round the table. */
const RING: SeatTone[] = ['confirmed', 'pending', 'maybe', 'declined'];

const strokes: Record<SeatTone, string> = {
    confirmed: 'stroke-success',
    pending: 'stroke-warning',
    maybe: 'stroke-info',
    declined: 'stroke-destructive',
    empty: 'stroke-foreground/10',
};

/** Taken seats by the RSVP status of whoever sits there. */
export function seatCounts(table: EventTable): Record<SeatTone, number> {
    const counts: Record<SeatTone, number> = {
        confirmed: 0,
        pending: 0,
        maybe: 0,
        declined: 0,
        empty: 0,
    };

    for (const seat of table.seats) {
        counts[seat.guest_id ? seatTone(seat.status) : 'empty'] += 1;
    }

    return counts;
}

/** "Table 3: 8 of 10 seated. 5 confirmed, 2 pending, 1 declined." */
function describe(table: EventTable, counts: Record<SeatTone, number>): string {
    const seated = table.seat_count - counts.empty;
    const parts = RING.filter((tone) => counts[tone] > 0).map(
        (tone) => `${counts[tone]} ${seatColors[tone].label.toLowerCase()}`,
    );

    return `${table.name}: ${seated} of ${table.seat_count} seated${parts.length ? `. ${parts.join(', ')}` : ''}.`;
}

/**
 * A table on the floor plan: its shape outlined by a ring showing how its
 * seats are filled (confirmed, pending, maybe, declined; the rest empty),
 * with the name and seats taken of the total in the middle.
 */
export function TableToken({
    table,
    className,
}: {
    table: EventTable;
    className?: string;
}) {
    const { width, height } = tableTokenSize(table.shape);
    const counts = seatCounts(table);
    const seated = table.seat_count - counts.empty;
    const stroke = 6;
    const inset = stroke / 2;
    const round = table.shape === 'round' || table.shape === 'oval';

    // Every segment is drawn along the same outline (pathLength 100),
    // offset by the segments before it.
    let offset = 0;
    const segments = RING.filter((tone) => counts[tone] > 0).map((tone) => {
        const length = (counts[tone] / table.seat_count) * 100;
        const segment = { tone, length, offset };
        offset += length;

        return segment;
    });

    const outline = (props: {
        className: string;
        dasharray?: string;
        dashoffset?: number;
    }) => {
        const common = {
            pathLength: 100,
            fill: 'none',
            strokeWidth: stroke,
            strokeDasharray: props.dasharray,
            strokeDashoffset: props.dashoffset,
            className: props.className,
        };

        return round ? (
            <ellipse
                cx={width / 2}
                cy={height / 2}
                rx={width / 2 - inset}
                ry={height / 2 - inset}
                {...common}
            />
        ) : (
            <rect
                x={inset}
                y={inset}
                width={width - stroke}
                height={height - stroke}
                rx={22}
                {...common}
            />
        );
    };

    return (
        <div
            role="img"
            aria-label={describe(table, counts)}
            title={describe(table, counts)}
            className={cn('relative', className)}
            style={{ width, height }}
        >
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="absolute inset-0 size-full"
            >
                {round ? (
                    <ellipse
                        cx={width / 2}
                        cy={height / 2}
                        rx={width / 2 - inset}
                        ry={height / 2 - inset}
                        className="fill-card"
                    />
                ) : (
                    <rect
                        x={inset}
                        y={inset}
                        width={width - stroke}
                        height={height - stroke}
                        rx={22}
                        className="fill-card"
                    />
                )}
                {outline({ className: strokes.empty })}
                {segments.map((segment) => (
                    <g key={segment.tone}>
                        {outline({
                            className: strokes[segment.tone],
                            dasharray: `${segment.length} ${100 - segment.length}`,
                            dashoffset: -segment.offset,
                        })}
                    </g>
                ))}
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center px-5 text-center">
                <span className="max-w-full truncate text-[22px] leading-tight font-bold">
                    {table.name}
                </span>
                <span className="mt-1 text-[20px] text-muted-foreground tabular-nums">
                    {seated}/{table.seat_count}
                </span>
            </div>
        </div>
    );
}
