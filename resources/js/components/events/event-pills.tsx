import type { LucideIcon } from 'lucide-react';
import {
    Baby,
    Briefcase,
    Cake,
    CalendarClock,
    CircleDashed,
    Gem,
    Heart,
    Hourglass,
    Mic2,
    PartyPopper,
    Sparkles,
    Users,
    X,
} from 'lucide-react';

import { daysUntil } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Event } from '@/types';

const typeIcons: Record<string, LucideIcon> = {
    wedding: Heart,
    engagement: Gem,
    birthday: Cake,
    baby_shower: Baby,
    anniversary: Sparkles,
    corporate: Briefcase,
    conference: Mic2,
    party: PartyPopper,
    community: Users,
};

/** "48 days to go", "Tomorrow", "Today", "Held 3 days ago", "Date TBC". */
function countdown(date: string | null): {
    label: string;
    soon: boolean;
    past: boolean;
} {
    if (!date) {
        return { label: 'Date TBC', soon: false, past: false };
    }

    const days = daysUntil(date);

    if (days < 0) {
        return {
            label: `Held ${-days} ${days === -1 ? 'day' : 'days'} ago`,
            soon: false,
            past: true,
        };
    }

    if (days <= 1) {
        return {
            label: days === 0 ? 'Today' : 'Tomorrow',
            soon: true,
            past: false,
        };
    }

    return { label: `${days} days to go`, soon: days <= 7, past: false };
}

const base =
    'inline-flex h-7 shrink-0 items-center gap-1.5 rounded-full px-2.5 text-xs font-semibold whitespace-nowrap ring-1 backdrop-blur-md sm:h-8 sm:px-3';

/**
 * The event's status, type and countdown as one row of frosted pills over the
 * poster image (phone and desktop). They share a height and wrap on narrow
 * screens.
 */
export function EventPills({
    event,
    className,
}: {
    event: Pick<
        Event,
        'state' | 'event_type' | 'event_type_label' | 'event_date'
    >;
    className?: string;
}) {
    const TypeIcon = typeIcons[event.event_type ?? ''] ?? CalendarClock;
    const when = countdown(event.event_date);

    return (
        <div
            className={cn(
                'flex flex-wrap items-center gap-1.5 sm:gap-2',
                className,
            )}
        >
            {event.state === 'published' ? (
                <span
                    className={cn(
                        base,
                        'bg-emerald-500/20 text-emerald-50 ring-emerald-300/40',
                    )}
                >
                    <span className="relative flex size-2">
                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-300 opacity-70" />
                        <span className="relative inline-flex size-2 rounded-full bg-emerald-300" />
                    </span>
                    Live
                </span>
            ) : event.state === 'draft' ? (
                <span
                    className={cn(base, 'bg-white/15 text-white ring-white/25')}
                >
                    <CircleDashed className="size-3.5" />
                    Draft
                </span>
            ) : (
                <span
                    className={cn(
                        base,
                        'bg-red-500/25 text-red-50 ring-red-300/40',
                    )}
                >
                    <X className="size-3.5" />
                    Cancelled
                </span>
            )}

            {event.event_type_label && (
                <span
                    className={cn(base, 'bg-white/15 text-white ring-white/25')}
                >
                    <TypeIcon className="size-3.5" />
                    {event.event_type_label}
                </span>
            )}

            <span
                className={cn(
                    base,
                    when.soon
                        ? 'bg-amber-400/25 text-amber-50 ring-amber-200/50'
                        : 'bg-white/15 text-white ring-white/25',
                    when.past && 'opacity-80',
                )}
            >
                <Hourglass className="size-3.5" />
                {when.label}
            </span>
        </div>
    );
}
