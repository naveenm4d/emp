import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import type { EventTable, SeatingGuest } from '@/types';

/** Free seats at the table, counting the guest's own seats there as free. */
export function freeSeatsFor(table: EventTable, guest?: SeatingGuest | null) {
    return table.seats.filter(
        (seat) => !seat.guest_id || (guest && seat.guest_id === guest.id),
    );
}

/**
 * Pick a table for some people. Tables with room for everyone are offered
 * as they are; tables with some room say how many fit (the caller decides,
 * e.g. by offering to split the party); full tables are disabled.
 */
export function TablePicker({
    open,
    onOpenChange,
    title,
    description,
    need,
    tables,
    freeAt,
    onPick,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    /** How many people need a seat. */
    need: number;
    tables: EventTable[];
    /** Free seats at a table for these people. */
    freeAt: (table: EventTable) => number;
    onPick: (table: EventTable, free: number) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                <ul className="min-h-0 flex-1 divide-y overflow-y-auto">
                    {tables.length === 0 && (
                        <li className="p-4 text-center text-sm text-muted-foreground">
                            No other tables. Add one first.
                        </li>
                    )}
                    {tables.map((table) => {
                        const free = freeAt(table);

                        return (
                            <li key={table.id}>
                                <button
                                    type="button"
                                    disabled={free === 0}
                                    onClick={() => {
                                        onPick(table, free);
                                        onOpenChange(false);
                                    }}
                                    className={cn(
                                        'flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left',
                                        free > 0
                                            ? 'hover:bg-muted'
                                            : 'cursor-not-allowed opacity-60',
                                    )}
                                >
                                    <span className="font-medium">
                                        {table.name}
                                    </span>
                                    <span
                                        className={cn(
                                            'text-xs',
                                            free > 0 && free < need
                                                ? 'text-amber-700 dark:text-amber-400'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {free === 0
                                            ? 'full'
                                            : free < need
                                              ? `${free} free · only ${free} of ${need} fit`
                                              : `${free} free`}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </DialogContent>
        </Dialog>
    );
}
