import { cn } from '@/lib/utils';

type ResponseBarProps = {
    attending: number;
    declined: number;
    total: number;
    /** 4px in lists, 6px on cards. */
    size?: 'sm' | 'md';
    /** Coloured key under the bar ("156 attending · 22 declined · 62 no reply"). */
    legend?: { waiting: number };
    className?: string;
};

function share(part: number, total: number): string {
    return `${total > 0 ? Math.min(100, (part / total) * 100) : 0}%`;
}

/** Stacked bar of replies: attending, then declined, on the empty track. */
export function ResponseBar({
    attending,
    declined,
    total,
    size = 'md',
    legend,
    className,
}: ResponseBarProps) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <div
                role="img"
                aria-label={`${attending} attending, ${declined} declined of ${total}`}
                className={cn(
                    'flex overflow-hidden rounded-full bg-foreground/6',
                    size === 'sm' ? 'h-1' : 'h-1.5',
                )}
            >
                <div
                    className="bg-success"
                    style={{ width: share(attending, total) }}
                />
                <div
                    className="bg-destructive"
                    style={{ width: share(declined, total) }}
                />
            </div>
            {legend && (
                <div className="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                    <Key className="bg-success">{attending} attending</Key>
                    <Key className="bg-destructive">{declined} declined</Key>
                    <Key className="bg-foreground/14">
                        {legend.waiting} no reply
                    </Key>
                </div>
            )}
        </div>
    );
}

function Key({
    className,
    children,
}: {
    className: string;
    children: React.ReactNode;
}) {
    return (
        <span className="flex items-center gap-1">
            <span className={cn('size-2 rounded-xs', className)} />
            {children}
        </span>
    );
}
