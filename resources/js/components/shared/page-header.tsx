import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type PageHeaderProps = {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
    /** Line above the title ("Nimali & Kasun's Wedding" on the guests page). */
    eyebrow?: ReactNode;
    /** Content under the title row (segmented control, search). */
    children?: ReactNode;
    className?: string;
};

/**
 * Page title row. On phones it becomes the full-width surface header of the
 * app screens; from `md` up it sits on the page background.
 */
export function PageHeader({
    title,
    description,
    actions,
    eyebrow,
    children,
    className,
}: PageHeaderProps) {
    return (
        <div
            className={cn(
                '-mx-3 -mt-4 mb-3 flex flex-col gap-3 border-b border-border bg-card px-4 pt-5 pb-3 md:mx-0 md:mt-0 md:mb-4 md:border-0 md:bg-transparent md:p-0',
                className,
            )}
        >
            <div className="flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                <div className="min-w-0">
                    {eyebrow && (
                        <div className="text-[13px] text-muted-foreground">
                            {eyebrow}
                        </div>
                    )}
                    <h1 className="text-2xl font-bold text-foreground">
                        {title}
                    </h1>
                    {description && (
                        <div className="text-[13px] text-muted-foreground">
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
            {children}
        </div>
    );
}
