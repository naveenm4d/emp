import { Popover } from '@base-ui/react/popover';
import { Clock, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';

const HOURS = [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
const MINUTES = Array.from({ length: 12 }, (_, index) => index * 5);
/** Common starts, one tap away. */
const QUICK = ['09:00', '12:00', '16:00', '18:30', '19:00', '20:00'];

type Parts = { hour: number; minute: number; pm: boolean };

/** "18:30" → 6, 30, pm. */
function parse(value: string): Parts | null {
    const match = /^(\d{2}):(\d{2})/.exec(value);

    if (!match) {
        return null;
    }

    const hours = +match[1];

    return { hour: hours % 12 || 12, minute: +match[2], pm: hours >= 12 };
}

function format({ hour, minute, pm }: Parts): string {
    const hours = (hour % 12) + (pm ? 12 : 0);

    return `${String(hours).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
}

type TimePickerProps = {
    id?: string;
    /** HH:MM (24h), or '' for none. */
    value: string;
    onChange: (value: string) => void;
    /** Earliest pickable time (HH:MM); earlier ones are greyed out. */
    min?: string;
    placeholder?: string;
    clearable?: boolean;
    disabled?: boolean;
    invalid?: boolean;
    className?: string;
};

/**
 * A time field in the dashboard theme: a button like an input that opens
 * columns of hours, minutes (5-minute steps) and AM / PM, with a few common
 * times to tap, instead of the browser's own picker.
 */
export function TimePicker({
    id,
    value,
    onChange,
    min,
    placeholder = 'Pick a time',
    clearable = false,
    disabled = false,
    invalid = false,
    className,
}: TimePickerProps) {
    const selected = parse(value);
    const [open, setOpen] = useState(false);
    const tooEarly = (time: string) => !!min && time < min;

    /** Changes one part; the others come from the current value (or 7:00 PM). */
    const set = (changes: Partial<Parts>) =>
        onChange(
            format({
                ...(selected ?? { hour: 7, minute: 0, pm: true }),
                ...changes,
            }),
        );

    return (
        <Popover.Root open={open} onOpenChange={setOpen}>
            <div className={cn('relative', className)}>
                <Popover.Trigger
                    id={id}
                    disabled={disabled}
                    aria-invalid={invalid || undefined}
                    className={cn(
                        'flex h-10 w-full min-w-0 items-center gap-2.5 rounded-lg border border-input bg-card px-3 text-left text-base transition-colors outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive data-popup-open:border-ring data-popup-open:ring-3 data-popup-open:ring-ring/50 md:text-sm',
                        clearable && selected && 'pr-9',
                    )}
                >
                    <Clock className="size-4 shrink-0 text-muted-foreground" />
                    <span
                        className={cn(
                            'truncate tabular-nums',
                            !selected && 'text-muted-foreground',
                        )}
                    >
                        {selected ? formatTime(value) : placeholder}
                    </span>
                </Popover.Trigger>
                {clearable && selected && !disabled && (
                    <button
                        type="button"
                        aria-label="Clear time"
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
                    <Popover.Popup className="w-72 rounded-2xl bg-popover p-3 text-popover-foreground shadow-xl ring-1 ring-border outline-none motion-safe:animate-fade-in">
                        <div className="mb-3 flex items-center justify-center gap-1 rounded-xl bg-raised py-2.5 text-2xl font-bold tabular-nums">
                            {selected ? (
                                formatTime(value)
                            ) : (
                                <span className="text-base font-semibold text-muted-foreground">
                                    {placeholder}
                                </span>
                            )}
                        </div>

                        <div className="grid grid-cols-[1fr_1fr_4rem] gap-2">
                            <Column label="Hour">
                                {HOURS.map((hour) => (
                                    <Option
                                        key={hour}
                                        selected={selected?.hour === hour}
                                        disabled={tooEarly(
                                            format({
                                                hour,
                                                minute: 59,
                                                pm: selected?.pm ?? true,
                                            }),
                                        )}
                                        onClick={() => set({ hour })}
                                    >
                                        {hour}
                                    </Option>
                                ))}
                            </Column>
                            <Column label="Minute">
                                {MINUTES.map((minute) => (
                                    <Option
                                        key={minute}
                                        selected={selected?.minute === minute}
                                        disabled={
                                            !!selected &&
                                            tooEarly(
                                                format({ ...selected, minute }),
                                            )
                                        }
                                        onClick={() => set({ minute })}
                                    >
                                        {String(minute).padStart(2, '0')}
                                    </Option>
                                ))}
                            </Column>
                            <div className="flex flex-col gap-1">
                                <span className="pb-1 text-center text-[11px] font-semibold text-subtle">
                                    &nbsp;
                                </span>
                                {[false, true].map((pm) => (
                                    <button
                                        key={String(pm)}
                                        type="button"
                                        aria-pressed={selected?.pm === pm}
                                        onClick={() => set({ pm })}
                                        className={cn(
                                            'h-10 rounded-xl text-sm font-bold transition-colors',
                                            selected?.pm === pm
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-raised hover:bg-foreground/8',
                                        )}
                                    >
                                        {pm ? 'PM' : 'AM'}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="mt-3 border-t border-border pt-3">
                            <div className="flex flex-wrap gap-1.5">
                                {QUICK.filter((time) => !tooEarly(time)).map(
                                    (time) => (
                                        <button
                                            key={time}
                                            type="button"
                                            onClick={() => {
                                                onChange(time);
                                                setOpen(false);
                                            }}
                                            className={cn(
                                                'h-7 rounded-full border px-2.5 text-xs font-semibold tabular-nums transition-colors',
                                                value === time
                                                    ? 'border-transparent bg-strong text-strong-foreground'
                                                    : 'border-input hover:bg-raised',
                                            )}
                                        >
                                            {formatTime(time)}
                                        </button>
                                    ),
                                )}
                            </div>
                            <div className="mt-2.5 flex justify-between">
                                {clearable && selected ? (
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
                                ) : (
                                    <span />
                                )}
                                <button
                                    type="button"
                                    onClick={() => setOpen(false)}
                                    className="h-8 rounded-full bg-primary px-4 text-xs font-bold text-primary-foreground"
                                >
                                    Done
                                </button>
                            </div>
                        </div>
                    </Popover.Popup>
                </Popover.Positioner>
            </Popover.Portal>
        </Popover.Root>
    );
}

/** A scrolling column of options; the selected one scrolls into view on open. */
function Column({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    const list = useRef<HTMLDivElement>(null);

    // Centre the selected option in its column (without scrolling the page).
    useEffect(() => {
        const box = list.current;
        const option = box?.querySelector<HTMLElement>('[aria-pressed="true"]');

        if (box && option) {
            box.scrollTop =
                option.offsetTop - box.clientHeight / 2 + option.clientHeight / 2;
        }
    }, []);

    return (
        <div className="flex min-w-0 flex-col">
            <span className="pb-1 text-center text-[11px] font-semibold text-subtle">
                {label}
            </span>
            <div
                ref={list}
                role="listbox"
                aria-label={label}
                className="flex h-44 flex-col gap-1 overflow-y-auto rounded-xl bg-raised p-1 [scrollbar-width:thin]"
            >
                {children}
            </div>
        </div>
    );
}

function Option({
    selected,
    disabled,
    onClick,
    children,
}: {
    selected: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="option"
            aria-selected={selected}
            aria-pressed={selected}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'h-9 shrink-0 rounded-lg text-sm font-semibold tabular-nums transition-colors disabled:pointer-events-none disabled:opacity-30',
                selected
                    ? 'bg-primary text-primary-foreground'
                    : 'hover:bg-card',
            )}
        >
            {children}
        </button>
    );
}
