import { ChevronDown } from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';

import { venueElementIcons } from '@/components/seating/floor-plan';
import type { SeatTone } from '@/components/seating/seat-colors';
import { SeatLegend } from '@/components/seating/table-card';
import { TableToken } from '@/components/seating/table-token';
import { StatusBadge } from '@/components/shared/status-badge';
import type { EventTable, VenueElementKind } from '@/types';

/**
 * Made-up screens shown blurred behind the upgrade prompt on locked tabs, so
 * clients see what they'd get. Never the event's data (the server sends none).
 */

const GUESTS = [
    ['Nimali Perera', '+94 77 214 3381'],
    ['Kasun Jayasinghe', '+94 71 508 9920'],
    ['Tharushi Fernando', '+94 76 330 1475'],
    ['Dilan Wickramasinghe', '+94 70 772 6613'],
    ['Ishara Silva', '+94 77 945 0082'],
    ['Ruwan Bandara', '+94 72 116 4759'],
    ['Sachini de Alwis', '+94 75 603 2218'],
    ['Chamath Gunawardena', '+94 78 890 3341'],
] as const;

// ---------------------------------------------------------------- Seating

const PLAN = { width: 1600, height: 900 };

/** A sample table: seat statuses in order, the rest empty. */
function sampleTable(
    number: number,
    seats: number,
    filled: Partial<Record<Exclude<SeatTone, 'empty'>, number>>,
): EventTable {
    const statuses = (
        ['confirmed', 'pending', 'maybe', 'declined'] as const
    ).flatMap((tone) => Array<typeof tone>(filled[tone] ?? 0).fill(tone));

    return {
        id: `sample-${number}`,
        name: `Table ${number}`,
        shape: 'round',
        seat_count: seats,
        x: null,
        y: null,
        seats: Array.from({ length: seats }, (_, index) => ({
            number: index + 1,
            guest_id: statuses[index] ? `sample-guest-${index}` : null,
            party_member: statuses[index] ? 0 : null,
            label: null,
            status: statuses[index] ?? null,
        })),
    };
}

const TABLES: { table: EventTable; x: number; y: number }[] = [
    [8, { confirmed: 8 }],
    [8, { confirmed: 6, pending: 2 }],
    [10, { confirmed: 7, maybe: 1 }],
    [8, { confirmed: 5, pending: 2, declined: 1 }],
    [10, { confirmed: 10 }],
    [8, { confirmed: 4, pending: 3 }],
    [8, { confirmed: 8 }],
    [10, { confirmed: 6, pending: 2, maybe: 1 }],
    [8, { confirmed: 3, pending: 2 }],
    [10, { confirmed: 9, declined: 1 }],
    [8, { confirmed: 5, maybe: 2 }],
    [8, { confirmed: 2, pending: 4 }],
].map(([seats, filled], index) => ({
    table: sampleTable(
        index + 1,
        seats as number,
        filled as Partial<Record<Exclude<SeatTone, 'empty'>, number>>,
    ),
    // Six tables each side of the dance floor, three columns by two rows.
    x: (index < 6 ? 70 : 1030) + (index % 3) * 170,
    y: 250 + Math.floor((index % 6) / 3) * 200,
}));

const ELEMENTS: {
    kind: VenueElementKind;
    label: string;
    x: number;
    y: number;
    width: number;
    height: number;
}[] = [
    { kind: 'stage', label: 'Stage', x: 560, y: 30, width: 480, height: 120 },
    {
        kind: 'poruwa',
        label: 'Poruwa',
        x: 710,
        y: 180,
        width: 180,
        height: 150,
    },
    {
        kind: 'dance_floor',
        label: 'Dance floor',
        x: 590,
        y: 370,
        width: 420,
        height: 250,
    },
    {
        kind: 'cake_table',
        label: 'Cake table',
        x: 900,
        y: 190,
        width: 120,
        height: 90,
    },
    { kind: 'dj', label: 'DJ / band', x: 1100, y: 40, width: 180, height: 100 },
    {
        kind: 'buffet',
        label: 'Buffet',
        x: 590,
        y: 680,
        width: 420,
        height: 100,
    },
    {
        kind: 'entrance',
        label: 'Entrance',
        x: 40,
        y: 800,
        width: 180,
        height: 70,
    },
    {
        kind: 'registration',
        label: 'Registration',
        x: 240,
        y: 800,
        width: 200,
        height: 70,
    },
    { kind: 'bar', label: 'Bar', x: 1340, y: 790, width: 220, height: 80 },
    {
        kind: 'photo_booth',
        label: 'Photo booth',
        x: 1360,
        y: 40,
        width: 200,
        height: 100,
    },
];

/** Scales a fixed-size drawing to the width of its box. */
function useFitWidth(width: number) {
    const box = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(0.5);

    useLayoutEffect(() => {
        const element = box.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setScale(entry.contentRect.width / width),
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, [width]);

    return { box, scale };
}

export function SeatingPreview() {
    const { box, scale } = useFitWidth(PLAN.width);

    return (
        <div className="flex flex-col gap-3">
            <SeatLegend />
            <div
                ref={box}
                className="relative overflow-hidden rounded-xl bg-raised"
                style={{ height: PLAN.height * scale }}
            >
                <div
                    className="absolute top-0 left-0 origin-top-left bg-card bg-[radial-gradient(var(--input)_1.5px,transparent_1.5px)] bg-size-[40px_40px]"
                    style={{
                        width: PLAN.width,
                        height: PLAN.height,
                        transform: `scale(${scale})`,
                    }}
                >
                    {ELEMENTS.map((element) => {
                        const Icon = venueElementIcons[element.kind];

                        return (
                            <div
                                key={element.kind}
                                className="absolute flex flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed border-input bg-raised text-muted-foreground"
                                style={{
                                    left: element.x,
                                    top: element.y,
                                    width: element.width,
                                    height: element.height,
                                }}
                            >
                                <Icon className="size-8" strokeWidth={1.75} />
                                <span className="text-[20px] font-semibold">
                                    {element.label}
                                </span>
                            </div>
                        );
                    })}
                    {TABLES.map(({ table, x, y }) => (
                        <div
                            key={table.id}
                            className="absolute"
                            style={{ left: x, top: y }}
                        >
                            <TableToken table={table} />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

// ------------------------------------------------------------------ RSVPs

const RSVPS: [status: string, message: string][] = [
    ['accepted', 'read'],
    ['accepted', 'read'],
    ['sent', 'delivered'],
    ['declined', 'read'],
    ['maybe', 'read'],
    ['accepted', 'delivered'],
    ['sent', 'sent'],
    ['accepted', 'read'],
];

export function RsvpPreview() {
    return (
        <div className="flex flex-col gap-3">
            <div className="flex h-9 w-full items-center justify-between rounded-md border border-input bg-card px-3 text-sm sm:w-44">
                All statuses
                <ChevronDown className="size-4 text-muted-foreground" />
            </div>
            <div className="overflow-hidden rounded-xl bg-card shadow-card">
                <div className="hidden grid-cols-[1.6fr_1fr_1fr] gap-4 border-b border-border px-4 py-2.5 text-xs font-semibold text-muted-foreground md:grid">
                    <span>Guest</span>
                    <span>Status</span>
                    <span>Message</span>
                </div>
                {GUESTS.map(([name, phone], index) => (
                    <div
                        key={name}
                        className="grid grid-cols-[1fr_auto] items-center gap-4 border-b border-border px-4 py-3 last:border-0 md:grid-cols-[1.6fr_1fr_1fr]"
                    >
                        <div className="min-w-0">
                            <div className="truncate text-sm font-medium">
                                {name}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {phone}
                            </div>
                        </div>
                        <div>
                            <StatusBadge status={RSVPS[index][0]} />
                        </div>
                        <div className="hidden md:block">
                            <StatusBadge
                                status={RSVPS[index][1]}
                                label={`Message ${RSVPS[index][1]}`}
                            />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// --------------------------------------------------------------- Messages

const MESSAGES: [status: string, sent: string, delivered: string][] = [
    ['read', 'Today, 9:14 AM', 'Today, 9:14 AM'],
    ['read', 'Today, 9:14 AM', 'Today, 9:15 AM'],
    ['delivered', 'Today, 8:02 AM', 'Today, 8:02 AM'],
    ['read', 'Yesterday, 6:40 PM', 'Yesterday, 6:40 PM'],
    ['failed', 'Yesterday, 6:40 PM', '—'],
    ['delivered', 'Yesterday, 11:20 AM', 'Yesterday, 11:21 AM'],
    ['read', '24 Sep, 7:05 PM', '24 Sep, 7:05 PM'],
    ['sent', '24 Sep, 7:00 PM', '—'],
];

export function MessagesPreview() {
    return (
        <div className="overflow-hidden rounded-lg bg-card shadow-card">
            <div className="grid grid-cols-[1.5fr_0.8fr_0.8fr_1fr_1fr] gap-4 border-b border-border px-4 py-2.5 text-xs font-semibold text-muted-foreground">
                <span>Guest</span>
                <span>Channel</span>
                <span>Status</span>
                <span>Sent</span>
                <span>Delivered</span>
            </div>
            {GUESTS.map(([name, phone], index) => {
                const [status, sent, delivered] = MESSAGES[index];

                return (
                    <div
                        key={name}
                        className="grid grid-cols-[1.5fr_0.8fr_0.8fr_1fr_1fr] items-center gap-4 border-b border-border px-4 py-3 text-sm last:border-0"
                    >
                        <div className="min-w-0">
                            <div className="truncate font-medium">{name}</div>
                            <div className="truncate text-xs text-muted-foreground">
                                {phone}
                            </div>
                        </div>
                        <span className="text-muted-foreground">WhatsApp</span>
                        <div>
                            <StatusBadge status={status} />
                        </div>
                        <span className="truncate text-muted-foreground">
                            {sent}
                        </span>
                        <span className="truncate text-muted-foreground">
                            {delivered}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}
