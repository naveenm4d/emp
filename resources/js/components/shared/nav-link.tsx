import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

type NavLinkProps = {
    href: string;
    label: string;
    icon: LucideIcon;
    active: boolean;
    badge?: number;
};

/** Sidebar navigation item (sidebar shared by both dashboards). */
export function NavLink({
    href,
    label,
    icon: Icon,
    active,
    badge,
}: NavLinkProps) {
    return (
        <Link
            href={href}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'flex items-center gap-2.5 rounded-md px-2.5 py-2.25 text-sm transition-colors',
                active
                    ? 'bg-sidebar-foreground/10 font-semibold text-sidebar-foreground shadow-[inset_3px_0_0_var(--sidebar-primary)]'
                    : 'text-sidebar-foreground/75 hover:bg-sidebar-accent hover:text-sidebar-foreground',
            )}
        >
            <Icon className="size-4.5 shrink-0" strokeWidth={1.75} />
            <span className="truncate">{label}</span>
            {!!badge && (
                <span className="ml-auto rounded-full bg-sidebar-primary px-1.75 py-px text-[11px] font-bold text-sidebar-primary-foreground">
                    {badge}
                </span>
            )}
        </Link>
    );
}

/** Icon-over-label item of the tablet rail. */
export function RailLink({
    href,
    label,
    icon: Icon,
    active,
}: Omit<NavLinkProps, 'badge'>) {
    return (
        <Link
            href={href}
            aria-current={active ? 'page' : undefined}
            title={label}
            className={cn(
                'flex w-13 flex-col items-center gap-0.75 rounded-lg py-2 text-[10px] font-semibold transition-colors',
                active
                    ? 'bg-sidebar-foreground/12 text-sidebar-foreground'
                    : 'text-sidebar-foreground/70 hover:text-sidebar-foreground',
            )}
        >
            <Icon className="size-5" strokeWidth={1.75} />
            <span className="w-full truncate text-center">{label}</span>
        </Link>
    );
}
