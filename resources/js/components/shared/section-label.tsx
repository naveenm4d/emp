import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/** Small uppercase heading above a group ("THIS MONTH", "APPEARANCE"). */
export function SectionLabel({
    children,
    action,
    className,
}: {
    children: ReactNode;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-center justify-between px-1 text-[11px] font-bold tracking-[0.08em] text-subtle uppercase',
                className,
            )}
        >
            <span>{children}</span>
            {action}
        </div>
    );
}
