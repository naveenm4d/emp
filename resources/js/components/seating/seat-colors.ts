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
        fill: 'fill-success',
        bg: 'bg-success',
        label: 'Confirmed',
    },
    pending: { fill: 'fill-warning', bg: 'bg-warning', label: 'Pending' },
    maybe: { fill: 'fill-info', bg: 'bg-info', label: 'Maybe' },
    declined: {
        fill: 'fill-destructive',
        bg: 'bg-destructive',
        label: 'Declined',
    },
    empty: {
        fill: 'fill-foreground/6 stroke-input',
        bg: 'bg-foreground/6 ring-1 ring-input',
        label: 'Empty',
    },
};
