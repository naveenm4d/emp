import { Link, router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus, Search } from 'lucide-react';
import { useState } from 'react';

import { DateTile } from '@/components/shared/date-tile';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { useClientCan } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { create, show } from '@/routes/client/events';
import type { Event } from '@/types';

type Chip = 'upcoming' | 'action' | 'drafts';

const CHIPS: { value: Chip; label: string }[] = [
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'action', label: 'Needs action' },
    { value: 'drafts', label: 'Drafts' },
];

/** "62 waiting · 4 failed", "8 to approve", "Draft". */
function summary(event: Event): { text: string; warning: boolean } {
    const parts = [
        event.waiting_count ? `${event.waiting_count} waiting` : null,
        event.failed_messages_count
            ? `${event.failed_messages_count} failed`
            : null,
    ].filter(Boolean);

    if (parts.length > 0) {
        return { text: parts.join(' · '), warning: false };
    }

    if (event.to_approve_count) {
        return { text: `${event.to_approve_count} to approve`, warning: true };
    }

    return {
        text:
            event.state === 'draft'
                ? 'Draft'
                : event.state === 'cancelled'
                  ? 'Cancelled'
                  : `${event.guests_count ?? 0} guests`,
        warning: false,
    };
}

function matches(event: Event, chip: Chip, search: string): boolean {
    if (search && !event.title.toLowerCase().includes(search.toLowerCase())) {
        return false;
    }

    if (chip === 'drafts') {
        return event.state === 'draft';
    }

    if (chip === 'action') {
        return (
            (event.to_approve_count ?? 0) > 0 ||
            (event.failed_messages_count ?? 0) > 0
        );
    }

    return true;
}

/**
 * Pill with the current event's name that opens "Switch event" (4b): the
 * client's upcoming events, loaded when the sheet opens.
 */
export function EventSwitcher({
    event,
    className,
}: {
    event: Pick<Event, 'id' | 'title'>;
    className?: string;
}) {
    const events = usePage().props.eventSwitcher;
    const can = useClientCan();
    const [open, setOpen] = useState(false);
    const [chip, setChip] = useState<Chip>('upcoming');
    const [search, setSearch] = useState('');

    const openSwitcher = () => {
        setOpen(true);
        router.reload({ only: ['eventSwitcher'] });
    };

    const list = (events ?? []).filter((item) => matches(item, chip, search));

    return (
        <>
            <button
                type="button"
                onClick={openSwitcher}
                className={cn(
                    'flex h-10 max-w-full min-w-0 items-center gap-2 rounded-full pr-1.5 pl-3.5 text-sm font-semibold outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                    className,
                )}
            >
                <span className="truncate">{event.title}</span>
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-current/14">
                    <ChevronsUpDown className="size-4" strokeWidth={1.75} />
                </span>
            </button>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent className="h-[86dvh] sm:h-auto">
                    <div className="flex flex-col gap-3 px-4 pt-3.5 pb-3">
                        <SheetTitle className="text-lg">
                            Switch event
                        </SheetTitle>
                        <label className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4.5 -translate-y-1/2 text-subtle" />
                            <input
                                type="search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={`Search ${events?.length ?? ''} events`}
                                className="h-11 w-full rounded-xl bg-background pr-3 pl-10 text-sm outline-none placeholder:text-subtle focus-visible:ring-3 focus-visible:ring-ring/50"
                            />
                        </label>
                        <div className="flex gap-2">
                            {CHIPS.map((item) => (
                                <button
                                    key={item.value}
                                    type="button"
                                    onClick={() => setChip(item.value)}
                                    className={cn(
                                        'inline-flex h-8 items-center rounded-full px-3 text-xs font-semibold',
                                        chip === item.value
                                            ? 'bg-strong text-strong-foreground'
                                            : 'border border-input',
                                    )}
                                >
                                    {item.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {events === undefined ? (
                            <div className="flex flex-col gap-2 px-4">
                                {[0, 1, 2].map((row) => (
                                    <div
                                        key={row}
                                        className="h-16 animate-pulse rounded-lg bg-foreground/6"
                                    />
                                ))}
                            </div>
                        ) : list.length === 0 ? (
                            <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                No events here.
                            </p>
                        ) : (
                            list.map((item) => {
                                const current = item.id === event.id;
                                const line = summary(item);

                                return (
                                    <Link
                                        key={item.id}
                                        href={show.url(item.id)}
                                        onClick={() => setOpen(false)}
                                        className={cn(
                                            'flex items-center gap-3 border-t border-border px-4 py-3 first:border-t-0',
                                            current && 'bg-raised',
                                        )}
                                    >
                                        <DateTile
                                            date={item.event_date}
                                            size="sm"
                                            variant={
                                                current ? 'strong' : 'outline'
                                            }
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span
                                                className={cn(
                                                    'block truncate text-sm',
                                                    current
                                                        ? 'font-bold'
                                                        : 'font-semibold',
                                                )}
                                            >
                                                {item.title}
                                            </span>
                                            <span
                                                className={cn(
                                                    'block text-xs',
                                                    line.warning
                                                        ? 'text-warning'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {line.text}
                                            </span>
                                        </span>
                                        {current && (
                                            <Check className="size-5 text-link" />
                                        )}
                                    </Link>
                                );
                            })
                        )}
                    </div>

                    {can('events.create') && (
                        <div className="border-t border-border px-4 pt-3 pb-7">
                            <Link
                                href={create.url()}
                                className="flex h-12 items-center justify-center gap-2 rounded-lg border border-dashed border-foreground/25 text-sm font-semibold"
                            >
                                <Plus className="size-4.5" /> Create event
                            </Link>
                        </div>
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}
