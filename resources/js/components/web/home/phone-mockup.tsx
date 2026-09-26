import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/** A CSS-only phone frame. The screen is whatever children you pass. */
export function PhoneMockup({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'bg-ink relative aspect-[9/19] w-[260px] rounded-[3rem] p-[10px] shadow-[0_50px_100px_-30px_rgb(22_19_15/0.55),0_30px_60px_-40px_rgb(176_141_87/0.6),inset_0_0_0_1.5px_rgb(255_255_255/0.08)] sm:w-[290px]',
                className,
            )}
        >
            {/* side buttons */}
            <span
                aria-hidden
                className="bg-ink-soft absolute top-28 -left-[3px] h-10 w-[3px] rounded-l"
            />
            <span
                aria-hidden
                className="bg-ink-soft absolute top-40 -left-[3px] h-14 w-[3px] rounded-l"
            />
            <span
                aria-hidden
                className="bg-ink-soft absolute top-32 -right-[3px] h-20 w-[3px] rounded-r"
            />

            <div className="bg-ivory relative h-full w-full overflow-hidden rounded-[2.4rem]">
                {/* dynamic island */}
                <span
                    aria-hidden
                    className="bg-ink absolute top-2.5 left-1/2 z-30 h-[22px] w-[84px] -translate-x-1/2 rounded-full"
                />
                {children}
            </div>
        </div>
    );
}
