import type { LucideIcon } from 'lucide-react';
import { Minus, Plus } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/*
 * Building blocks of the dashboard's settings screens (the event's Settings
 * and RSVP form tabs): icon-headed section cards, rows with a switch, and a
 * − / + stepper.
 */

/** A settings card: icon tile, title and what it's for. */
export function Section({
    icon: Icon,
    title,
    description,
    aside,
    children,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    aside?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="min-w-0 rounded-3xl bg-card p-4 shadow-card sm:p-5 lg:p-6">
            <div className="mb-4 flex items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                    <Icon className="size-5" strokeWidth={1.9} />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="flex items-center gap-2 text-base font-bold">
                        {title}
                        {aside}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                </div>
            </div>
            {children}
        </section>
    );
}

/** A setting with a switch; `children` show under it (like the plus-ones limit). */
export function ToggleRow({
    label,
    hint,
    checked,
    onChange,
    first,
    children,
}: {
    label: string;
    hint?: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    first?: boolean;
    children?: ReactNode;
}) {
    return (
        <div className={cn('py-3 first:pt-0 last:pb-0', first && 'pt-0')}>
            <label className="flex cursor-pointer items-center justify-between gap-4">
                <span className="min-w-0">
                    <span className="block text-sm font-semibold">{label}</span>
                    {hint && (
                        <span className="block text-xs text-muted-foreground">
                            {hint}
                        </span>
                    )}
                </span>
                <Switch checked={checked} onChange={onChange} />
            </label>
            {children}
        </div>
    );
}

export function Switch({
    checked,
    onChange,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <span className="relative inline-flex shrink-0">
            <input
                type="checkbox"
                role="switch"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                className="peer sr-only"
            />
            <span className="h-6.5 w-11 rounded-full bg-foreground/14 transition-colors peer-checked:bg-primary peer-focus-visible:ring-3 peer-focus-visible:ring-ring/50 peer-disabled:opacity-50" />
            <span className="absolute top-0.75 left-0.75 size-5 rounded-full bg-card shadow-sm transition-transform peer-checked:translate-x-4.5" />
        </span>
    );
}

/** − value + */
export function Stepper({
    label,
    value,
    min,
    max,
    onChange,
}: {
    label: string;
    value: number;
    min: number;
    max: number;
    onChange: (value: number) => void;
}) {
    const button =
        'flex size-8 items-center justify-center rounded-full bg-card shadow-card disabled:opacity-40';

    return (
        <span className="flex items-center gap-3" aria-label={label}>
            <button
                type="button"
                aria-label="Fewer"
                className={button}
                disabled={value <= min}
                onClick={() => onChange(Math.max(min, value - 1))}
            >
                <Minus className="size-4" />
            </button>
            <span className="w-6 text-center text-base font-bold tabular-nums">
                {value}
            </span>
            <button
                type="button"
                aria-label="More"
                className={button}
                disabled={value >= max}
                onClick={() => onChange(Math.min(max, value + 1))}
            >
                <Plus className="size-4" />
            </button>
        </span>
    );
}
