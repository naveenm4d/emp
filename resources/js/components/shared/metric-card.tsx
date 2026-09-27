import type { LucideIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

type MetricCardProps = {
    title: string;
    value: string | number;
    icon?: LucideIcon;
    description?: string;
    tone?: 'default' | 'danger';
    className?: string;
};

export function MetricCard({
    title,
    value,
    icon: Icon,
    description,
    tone = 'default',
    className,
}: MetricCardProps) {
    return (
        <div
            className={cn(
                'space-y-2 rounded-xl bg-card p-4 shadow-card',
                className,
            )}
        >
            <div className="flex items-center justify-between">
                <p className="text-xs font-semibold text-muted-foreground">
                    {title}
                </p>
                {Icon && (
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-raised">
                        <Icon
                            className="h-4 w-4 text-muted-foreground"
                            strokeWidth={1.75}
                        />
                    </div>
                )}
            </div>
            <div>
                <p
                    className={cn(
                        'text-[26px] leading-tight font-bold tabular-nums',
                        tone === 'danger'
                            ? 'text-destructive'
                            : 'text-foreground',
                    )}
                >
                    {value}
                </p>
                {description && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}
