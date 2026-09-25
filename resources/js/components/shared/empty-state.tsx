import type { LucideIcon } from 'lucide-react';
import { SearchX } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type EmptyStateProps = {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
};

export function EmptyState({
    icon: Icon = SearchX,
    title,
    description,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center px-4 py-16 text-center',
                className,
            )}
        >
            <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl border border-border bg-muted">
                <Icon
                    className="h-5 w-5 text-muted-foreground"
                    strokeWidth={1.5}
                />
            </div>
            <h3 className="mb-1 text-sm font-semibold text-foreground">
                {title}
            </h3>
            {description && (
                <p className="max-w-xs text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
