import { Search } from 'lucide-react';
import { useState } from 'react';

import { StatusBadge } from '@/components/shared/status-badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { SeatingGuest } from '@/types';

type GuestPickerProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    guests: SeatingGuest[];
    /** Why a guest can't be picked, or null when they can. */
    disabledReason?: (guest: SeatingGuest) => string | null;
    /** What picking this guest will do, when it's more than seating them (e.g. splitting the party). */
    note?: (guest: SeatingGuest) => string | null;
    onPick: (guest: SeatingGuest) => void;
};

/** A searchable list of guests with their party size, status and table. */
export function GuestPicker({
    open,
    onOpenChange,
    title,
    description,
    guests,
    disabledReason,
    note,
    onPick,
}: GuestPickerProps) {
    const [query, setQuery] = useState('');
    const shown = guests.filter((guest) =>
        guest.name.toLowerCase().includes(query.trim().toLowerCase()),
    );

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);
                setQuery('');
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                <div className="border-b p-3">
                    <div className="relative">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            autoFocus
                            aria-label="Search guests"
                            placeholder="Search guests"
                            className="pl-8"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                    </div>
                </div>
                <ul className="min-h-0 flex-1 divide-y overflow-y-auto">
                    {shown.length === 0 && (
                        <li className="p-4 text-center text-sm text-muted-foreground">
                            No guests to show.
                        </li>
                    )}
                    {shown.map((guest) => {
                        const reason = disabledReason?.(guest) ?? null;

                        return (
                            <li key={guest.id}>
                                <button
                                    type="button"
                                    disabled={reason !== null}
                                    onClick={() => {
                                        onPick(guest);
                                        onOpenChange(false);
                                        setQuery('');
                                    }}
                                    className={cn(
                                        'flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left transition-colors',
                                        reason === null
                                            ? 'hover:bg-muted'
                                            : 'cursor-not-allowed opacity-60',
                                    )}
                                >
                                    <span className="min-w-0">
                                        <span className="block truncate font-medium">
                                            {guest.name}
                                            {guest.party_size > 1 && (
                                                <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                    party of {guest.party_size}
                                                </span>
                                            )}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {reason ??
                                                note?.(guest) ??
                                                (guest.table_name
                                                    ? `At ${guest.table_name}`
                                                    : 'No seat yet')}
                                        </span>
                                    </span>
                                    <StatusBadge status={guest.rsvp_status} />
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </DialogContent>
        </Dialog>
    );
}
