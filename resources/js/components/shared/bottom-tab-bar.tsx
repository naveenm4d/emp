import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Lock } from 'lucide-react';

import { cn } from '@/lib/utils';

export type BottomTab = {
    label: string;
    icon: LucideIcon;
    active?: boolean;
    badge?: number;
    /** Not in the client's plan: shows a small lock on the icon. */
    locked?: boolean;
} & ({ href: string; onClick?: never } | { onClick: () => void; href?: never });

type BottomTabBarProps = {
    items: BottomTab[];
    /** Raised round action in the middle of the bar (the "+" of 4a). */
    fab?: {
        label: string;
        icon: LucideIcon;
        href?: string;
        disabled?: boolean;
        title?: string;
    };
};

/** Phone navigation pinned to the bottom of the screen (below `md`). */
export function BottomTabBar({ items, fab }: BottomTabBarProps) {
    const middle = Math.ceil(items.length / 2);
    const cells = fab
        ? [...items.slice(0, middle), null, ...items.slice(middle)]
        : items;

    return (
        <nav
            className="fixed inset-x-0 bottom-0 z-40 grid border-t border-border bg-card pt-2.5 pb-[max(env(safe-area-inset-bottom),14px)] text-[10px] font-semibold text-subtle md:hidden"
            style={{ gridTemplateColumns: `repeat(${cells.length}, 1fr)` }}
        >
            {cells.map((item, index) =>
                item === null ? (
                    <Fab key="fab" {...fab!} />
                ) : (
                    <Tab key={item.label + index} item={item} />
                ),
            )}
        </nav>
    );
}

function Tab({ item }: { item: BottomTab }) {
    const Icon = item.icon;
    const className = cn(
        'relative flex flex-col items-center gap-1 outline-none focus-visible:text-foreground',
        item.active && 'text-foreground',
    );
    const content = (
        <>
            <Icon className="size-6" strokeWidth={1.75} />
            {item.label}
            {!!item.badge && (
                <span className="absolute -top-1 left-1/2 ml-1 rounded-full bg-primary px-1.25 text-[10px] leading-4 text-primary-foreground">
                    {item.badge}
                </span>
            )}
            {item.locked && (
                <span className="absolute -top-1 left-1/2 ml-1.5 flex size-4 items-center justify-center rounded-full bg-card">
                    <Lock aria-label="Upgrade to unlock" className="size-2.5" />
                </span>
            )}
        </>
    );

    if (item.href !== undefined) {
        return (
            <Link
                href={item.href}
                className={className}
                aria-current={item.active ? 'page' : undefined}
            >
                {content}
            </Link>
        );
    }

    return (
        <button type="button" onClick={item.onClick} className={className}>
            {content}
        </button>
    );
}

function Fab({
    label,
    icon: Icon,
    href,
    disabled,
    title,
}: NonNullable<BottomTabBarProps['fab']>) {
    const className =
        'flex size-13 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-[0_6px_14px_color-mix(in_srgb,var(--primary)_35%,transparent)]';

    return (
        <div className="-mt-5.5 flex justify-center">
            {href && !disabled ? (
                <Link href={href} className={className} aria-label={label}>
                    <Icon className="size-6" strokeWidth={1.75} />
                </Link>
            ) : (
                <button
                    type="button"
                    disabled
                    title={title}
                    aria-label={label}
                    className={cn(className, 'opacity-50')}
                >
                    <Icon className="size-6" strokeWidth={1.75} />
                </button>
            )}
        </div>
    );
}
