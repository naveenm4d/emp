import { dateTileParts } from '@/lib/format';
import { cn } from '@/lib/utils';

const sizes = {
    sm: 'h-11.5 w-10.5 [&>b]:text-lg',
    md: 'h-13 w-12 [&>b]:text-xl',
    lg: 'h-14 w-13 [&>b]:text-[22px]',
};

type DateTileProps = {
    date: string | null;
    /** `strong` = filled (the nearest / selected event), `outline` otherwise. */
    variant?: 'strong' | 'outline';
    size?: keyof typeof sizes;
    className?: string;
};

/** Calendar tile: short month over the day ("NOV / 14"). */
export function DateTile({
    date,
    variant = 'outline',
    size = 'md',
    className,
}: DateTileProps) {
    const parts = date ? dateTileParts(date) : { month: 'TBC', day: '—' };

    return (
        <div
            className={cn(
                'flex shrink-0 flex-col items-center justify-center rounded-lg',
                variant === 'strong'
                    ? 'bg-strong text-strong-foreground'
                    : 'border border-input',
                sizes[size],
                className,
            )}
        >
            <span
                className={cn(
                    'text-[10px] font-bold',
                    variant === 'strong' ? 'text-primary' : 'text-link',
                )}
            >
                {parts.month}
            </span>
            <b className="leading-none font-bold">{parts.day}</b>
        </div>
    );
}
