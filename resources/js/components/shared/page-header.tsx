import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type PageHeaderProps = {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
    className?: string;
};

export function PageHeader({
    title,
    description,
    actions,
    className,
}: PageHeaderProps) {
    return (
        <div
            className={cn(
                'mb-6 flex flex-wrap items-start justify-between gap-4',
                className,
            )}
        >
            <div className="min-w-0">
                <h2 className="text-xl font-semibold tracking-tight text-foreground">
                    {title}
                </h2>
                {description && (
                    <div className="mt-0.5 text-sm text-muted-foreground">
                        {description}
                    </div>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
