import { Popover } from '@base-ui/react/popover';
import { CalendarDays, ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useState } from 'react';

import { todayInAppTimeZone } from '@/lib/format';
import { cn } from '@/lib/utils';

const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

type Ymd = { year: number; month: number; day: number };

/** "2026-11-14" → parts (no time zone involved). */
function parse(value: string | null | undefined): Ymd | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value ?? '');

    return match
        ? { year: +match[1], month: +match[2] - 1, day: +match[3] }
        : null;
}

function format({ year, month, day }: Ymd): string {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

/** "Sat, 14 Nov 2026" */
function label(value: Ymd): string {
    return new Date(Date.UTC(value.year, value.month, value.day)).toLocaleDateString(
        'en-GB',
        {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone: 'UTC',
        },
    );
}

function monthName(month: number, style: 'long' | 'short' = 'long'): string {
    return new Date(Date.UTC(2000, month, 1)).toLocaleDateString('en-GB', {
        month: style,
        timeZone: 'UTC',
    });
}

/** The days to draw for a month: leading blanks (Monday first), then 1..n. */
function monthCells(year: number, month: number): (number | null)[] {
    const first = new Date(Date.UTC(year, month, 1)).getUTCDay();
    const blanks = (first + 6) % 7;
    const days = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();

    return [
        ...Array<null>(blanks).fill(null),
        ...Array.from({ length: days }, (_, index) => index + 1),
    ];
}

type DatePickerProps = {
    id?: string;
    /** YYYY-MM-DD, or '' for none. */
    value: string;
    onChange: (value: string) => void;
    /** Earliest / latest pickable day (YYYY-MM-DD). */
    min?: string;
    max?: string;
    placeholder?: string;
    /** Show a ✕ that clears the date. */
    clearable?: boolean;
    disabled?: boolean;
    invalid?: boolean;
    className?: string;
};

/**
 * A date field in the dashboard theme: a button like an input that opens a
 * calendar (month view, with a quick month / year view), instead of the
 * browser's own picker.
 */
export function DatePicker({
    id,
    value,
    onChange,
    min,
    max,
    placeholder = 'Pick a date',
    clearable = false,
    disabled = false,
    invalid = false,
    className,
}: DatePickerProps) {
    const selected = parse(value);
    const today = parse(todayInAppTimeZone())!;
    const [open, setOpen] = useState(false);
    const [view, setView] = useState<{ year: number; month: number }>(
        () => selected ?? today,
    );
    const [picking, setPicking] = useState<'days' | 'months'>('days');
    const minDay = min ? format(parse(min)!) : null;
    const maxDay = max ? format(parse(max)!) : null;
    const outOfRange = (day: string) =>
        (minDay !== null && day < minDay) || (maxDay !== null && day > maxDay);

    const shift = (by: number) =>
        setView(({ year, month }) => {
            const next = month + by;

            return {
                year: year + Math.floor(next / 12),
                month: ((next % 12) + 12) % 12,
            };
        });

    const pick = (day: Ymd) => {
        onChange(format(day));
        setOpen(false);
    };

    return (
        <Popover.Root
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    setView(selected ?? today);
                    setPicking('days');
                }
            }}
        >
            <div className={cn('relative', className)}>
                <Popover.Trigger
                    id={id}
                    disabled={disabled}
                    aria-invalid={invalid || undefined}
                    className={cn(
                        'flex h-10 w-full min-w-0 items-center gap-2.5 rounded-lg border border-input bg-card px-3 text-left text-base outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive data-popup-open:border-ring data-popup-open:ring-3 data-popup-open:ring-ring/50 md:text-sm',
                        clearable && selected && 'pr-9',
                    )}
                >
                    <CalendarDays className="size-4 shrink-0 text-muted-foreground" />
                    <span
                        className={cn(
                            'truncate',
                            !selected && 'text-muted-foreground',
                        )}
                    >
                        {selected ? label(selected) : placeholder}
                    </span>
                </Popover.Trigger>
                {clearable && selected && !disabled && (
                    <button
                        type="button"
                        aria-label="Clear date"
                        onClick={() => onChange('')}
                        className="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground hover:bg-raised hover:text-foreground"
                    >
                        <X className="size-3.5" />
                    </button>
                )}
            </div>

            <Popover.Portal>
                <Popover.Positioner
                    side="bottom"
                    align="start"
                    sideOffset={6}
                    className="z-50"
                >
                    <Popover.Popup className="w-76 rounded-2xl bg-popover p-3 text-popover-foreground shadow-xl ring-1 ring-border outline-none motion-safe:animate-fade-in">
                        <div className="mb-2 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                aria-label={
                                    picking === 'days'
                                        ? 'Previous month'
                                        : 'Previous year'
                                }
                                onClick={() =>
                                    shift(picking === 'days' ? -1 : -12)
                                }
                                className="flex size-8 items-center justify-center rounded-full hover:bg-raised"
                            >
                                <ChevronLeft className="size-4" />
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    setPicking((mode) =>
                                        mode === 'days' ? 'months' : 'days',
                                    )
                                }
                                className="h-8 rounded-full px-3 text-sm font-bold hover:bg-raised"
                            >
                                {picking === 'days'
                                    ? `${monthName(view.month)} ${view.year}`
                                    : view.year}
                            </button>
                            <button
                                type="button"
                                aria-label={
                                    picking === 'days' ? 'Next month' : 'Next year'
                                }
                                onClick={() =>
                                    shift(picking === 'days' ? 1 : 12)
                                }
                                className="flex size-8 items-center justify-center rounded-full hover:bg-raised"
                            >
                                <ChevronRight className="size-4" />
                            </button>
                        </div>

                        {picking === 'days' ? (
                            <>
                                <div className="grid grid-cols-7 pb-1">
                                    {WEEKDAYS.map((weekday) => (
                                        <span
                                            key={weekday}
                                            className="py-1 text-center text-[11px] font-semibold text-subtle"
                                        >
                                            {weekday}
                                        </span>
                                    ))}
                                </div>
                                <div className="grid grid-cols-7 gap-0.5">
                                    {monthCells(view.year, view.month).map(
                                        (day, index) => {
                                            if (day === null) {
                                                return <span key={`b${index}`} />;
                                            }

                                            const cell = { ...view, day };
                                            const key = format(cell);
                                            const isSelected =
                                                !!selected &&
                                                format(selected) === key;
                                            const isToday =
                                                format(today) === key;

                                            return (
                                                <button
                                                    key={key}
                                                    type="button"
                                                    disabled={outOfRange(key)}
                                                    aria-pressed={isSelected}
                                                    aria-label={label(cell)}
                                                    onClick={() => pick(cell)}
                                                    className={cn(
                                                        'relative flex aspect-square items-center justify-center rounded-full text-sm tabular-nums transition-colors disabled:pointer-events-none disabled:text-subtle/50',
                                                        isSelected
                                                            ? 'bg-primary font-bold text-primary-foreground'
                                                            : 'hover:bg-raised',
                                                        isToday &&
                                                            !isSelected &&
                                                            'font-bold text-link',
                                                    )}
                                                >
                                                    {day}
                                                    {isToday && !isSelected && (
                                                        <span className="absolute bottom-1 size-1 rounded-full bg-link" />
                                                    )}
                                                </button>
                                            );
                                        },
                                    )}
                                </div>
                            </>
                        ) : (
                            <div className="grid grid-cols-3 gap-1.5 py-1">
                                {Array.from({ length: 12 }, (_, month) => {
                                    const current =
                                        !!selected &&
                                        selected.year === view.year &&
                                        selected.month === month;

                                    return (
                                        <button
                                            key={month}
                                            type="button"
                                            onClick={() => {
                                                setView({ ...view, month });
                                                setPicking('days');
                                            }}
                                            className={cn(
                                                'h-10 rounded-xl text-sm font-semibold transition-colors',
                                                current
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'hover:bg-raised',
                                            )}
                                        >
                                            {monthName(month, 'short')}
                                        </button>
                                    );
                                })}
                            </div>
                        )}

                        <div className="mt-2 flex items-center justify-between border-t border-border pt-2">
                            <button
                                type="button"
                                disabled={outOfRange(format(today))}
                                onClick={() => pick(today)}
                                className="h-8 rounded-full px-3 text-xs font-semibold text-link hover:bg-raised disabled:opacity-40"
                            >
                                Today
                            </button>
                            {clearable && selected && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        onChange('');
                                        setOpen(false);
                                    }}
                                    className="h-8 rounded-full px-3 text-xs font-semibold text-muted-foreground hover:bg-raised"
                                >
                                    Clear
                                </button>
                            )}
                        </div>
                    </Popover.Popup>
                </Popover.Positioner>
            </Popover.Portal>
        </Popover.Root>
    );
}
