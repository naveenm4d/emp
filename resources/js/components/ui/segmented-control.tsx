import { cn } from '@/lib/utils';

type Segment<T extends string> = { value: T; label: string; count?: number };

type SegmentedControlProps<T extends string> = {
    items: Segment<T>[];
    value: T;
    onChange: (value: T) => void;
    /** `pill` on phone headers, `box` in desktop filter rows. */
    variant?: 'pill' | 'box';
    label: string;
    className?: string;
};

/** One-of-a-few switch (Upcoming / Drafts / Past, Map / List). */
export function SegmentedControl<T extends string>({
    items,
    value,
    onChange,
    variant = 'pill',
    label,
    className,
}: SegmentedControlProps<T>) {
    return (
        <div
            role="tablist"
            aria-label={label}
            className={cn(
                'flex p-0.75 text-[13px] font-semibold',
                variant === 'pill'
                    ? 'rounded-full bg-foreground/6'
                    : 'w-fit rounded-lg border border-input bg-card',
                className,
            )}
        >
            {items.map((item) => {
                const selected = item.value === value;

                return (
                    <button
                        key={item.value}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        onClick={() => onChange(item.value)}
                        className={cn(
                            'transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring/50',
                            variant === 'pill'
                                ? 'flex-1 rounded-full p-1.75'
                                : 'rounded-md px-3 py-1.5',
                            selected
                                ? variant === 'pill'
                                    ? 'bg-card text-foreground shadow-[0_6px_18px_rgba(0,0,0,0.12)] dark:shadow-[0_6px_18px_rgba(0,0,0,0.5)]'
                                    : 'bg-raised text-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {item.label}
                        {item.count !== undefined && ` ${item.count}`}
                    </button>
                );
            })}
        </div>
    );
}
