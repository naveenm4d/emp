import type { GuestRsvpStatus } from '@/types';

/** How a seat looks for the status of the guest sitting there. */
export type SeatTone = 'confirmed' | 'pending' | 'maybe' | 'declined' | 'empty';

export function seatTone(status: GuestRsvpStatus | null): SeatTone {
    switch (status) {
        case null:
            return 'empty';
        case 'confirmed':
            return 'confirmed';
        case 'declined':
            return 'declined';
        case 'maybe':
            return 'maybe';
        default:
            // not_sent / pending: no answer yet.
            return 'pending';
    }
}

/** Fill colours for seat dots (SVG `fill-*` and HTML `bg-*`). */
export const seatColors: Record<
    SeatTone,
    { fill: string; bg: string; label: string }
> = {
    confirmed: {
        fill: 'fill-emerald-500',
        bg: 'bg-emerald-500',
        label: 'Confirmed',
    },
    pending: { fill: 'fill-amber-400', bg: 'bg-amber-400', label: 'Pending' },
    maybe: { fill: 'fill-sky-500', bg: 'bg-sky-500', label: 'Maybe' },
    declined: { fill: 'fill-red-500', bg: 'bg-red-500', label: 'Declined' },
    empty: {
        fill: 'fill-muted stroke-border',
        bg: 'bg-muted ring-1 ring-border',
        label: 'Empty',
    },
};
