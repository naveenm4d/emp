import type { CSSProperties, ElementType, ReactNode } from 'react';
import { Fragment, useRef } from 'react';

import { cn } from '@/lib/utils';

import { useInView } from './hooks';

/** Fades and lifts its children in the first time they scroll into view. */
export function Reveal({
    children,
    delay = 0,
    as: Tag = 'div',
    className,
}: {
    children: ReactNode;
    delay?: number;
    as?: ElementType;
    className?: string;
}) {
    const ref = useRef<HTMLElement>(null);
    const shown = useInView(ref, { threshold: 0.15 });

    return (
        <Tag
            ref={ref}
            data-shown={shown}
            className={cn('reveal', className)}
            style={{ '--delay': `${delay}ms` } as CSSProperties}
        >
            {children}
        </Tag>
    );
}

/**
 * Headline that rises in word by word. Words wrapped in *asterisks* get the
 * gold shimmer in italic.
 */
export function SplitText({
    text,
    as: Tag = 'h2',
    className,
    delay = 0,
    stagger = 70,
}: {
    text: string;
    as?: ElementType;
    className?: string;
    delay?: number;
    stagger?: number;
}) {
    const ref = useRef<HTMLElement>(null);
    const shown = useInView(ref, { threshold: 0.3 });
    const words = text.split(' ');

    return (
        <Tag
            ref={ref}
            data-shown={shown}
            className={className}
            aria-label={text.replaceAll('*', '')}
        >
            {words.map((word, i) => {
                const accent = word.startsWith('*');
                const clean = word.replaceAll('*', '');

                return (
                    <Fragment key={i}>
                        <span aria-hidden className="split-word">
                            <span
                                className={cn(
                                    accent && 'text-shimmer pr-[0.06em] italic',
                                )}
                                style={
                                    {
                                        '--delay': `${delay + i * stagger}ms`,
                                    } as CSSProperties
                                }
                            >
                                {clean}
                            </span>
                        </span>
                        {i < words.length - 1 && ' '}
                    </Fragment>
                );
            })}
        </Tag>
    );
}
