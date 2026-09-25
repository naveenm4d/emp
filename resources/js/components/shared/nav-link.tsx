import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

type NavLinkProps = {
    href: string;
    label: string;
    icon: LucideIcon;
    active: boolean;
};

/** Sidebar navigation item (dark sidebar shared by both dashboards). */
export function NavLink({ href, label, icon: Icon, active }: NavLinkProps) {
    return (
        <Link
            href={href}
            className={cn(
                'group relative flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                active
                    ? 'bg-white/[0.06] text-white'
                    : 'text-white/50 hover:bg-white/[0.04] hover:text-white/80',
            )}
        >
            {active && (
                <span className="absolute top-1/2 left-0 h-5 w-0.5 -translate-y-1/2 rounded-full bg-indigo-400" />
            )}
            <Icon
                className={cn(
                    'h-4 w-4 shrink-0',
                    active
                        ? 'text-indigo-400'
                        : 'text-white/40 group-hover:text-white/60',
                )}
                strokeWidth={1.75}
            />
            <span className="truncate">{label}</span>
        </Link>
    );
}
