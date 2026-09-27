import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { LogOut, Menu, Monitor, Moon, Sun, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import { FlashMessages } from '@/components/shared/flash-messages';
import { NavLink, RailLink } from '@/components/shared/nav-link';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/lib/appearance';
import { useAppearance } from '@/lib/appearance';
import { initials } from '@/lib/format';
import { cn } from '@/lib/utils';

export type NavItem = {
    label: string;
    href: string;
    icon: LucideIcon;
    /** Only highlight on this exact URL, not on nested pages. */
    exact?: boolean;
    /** Count pill shown after the label (e.g. failed messages). */
    badge?: number;
    /** Label under the icon in the tablet rail, when the full one is long. */
    shortLabel?: string;
};

type DashboardShellProps = {
    nav: NavItem[];
    user: { name: string; subtitle: string };
    logoutHref: string;
    /** Small tag next to the brand (e.g. "STAFF"). */
    badge?: string;
    /**
     * Where the avatar leads. Without it, the sidebar shows an account
     * block with the theme choice and sign out (staff console).
     */
    accountHref?: string;
    /** Bar above the page content from `md` up (staff search). */
    header?: ReactNode;
    /** Phone navigation; without it, phones get a top bar and a drawer. */
    bottomBar?: ReactNode;
    /** Drop the sidebar / rail (event pages bring their own header). */
    hideNav?: boolean;
    children: ReactNode;
};

export function isNavActive(
    path: string,
    item: Pick<NavItem, 'href' | 'exact'>,
) {
    const target = new URL(item.href, 'http://x').pathname;

    return path === target || (!item.exact && path.startsWith(`${target}/`));
}

/**
 * Frame shared by the client dashboard and the staff console: a sidebar on
 * desktop, an icon rail on tablets and a bottom tab bar (or drawer) on
 * phones. Each area supplies its own navigation and account block.
 */
export function DashboardShell({
    nav,
    user,
    logoutHref,
    badge,
    accountHref,
    header,
    bottomBar,
    hideNav = false,
    children,
}: DashboardShellProps) {
    const { url } = usePage();
    const [open, setOpen] = useState(false);
    const path = url.split('?')[0];

    return (
        <div className="min-h-screen bg-background">
            {!bottomBar && (
                <div className="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-border bg-card px-4 md:hidden">
                    <Brand badge={badge} />
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        className="rounded-md p-2 hover:bg-raised"
                        aria-label="Open menu"
                    >
                        <Menu className="size-5" />
                    </button>
                </div>
            )}

            {open && (
                <div
                    className="fixed inset-0 z-40 bg-black/60 lg:hidden"
                    onClick={() => setOpen(false)}
                />
            )}

            {/* Desktop sidebar; also the phone drawer when there's no tab bar. */}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-50 w-58 flex-col gap-6 bg-sidebar px-3 py-5 text-sidebar-foreground transition-transform lg:flex lg:translate-x-0',
                    open ? 'flex translate-x-0' : 'hidden -translate-x-full',
                    hideNav && 'lg:hidden',
                )}
            >
                <div className="flex items-center justify-between">
                    <Brand badge={badge} inverse />
                    <button
                        type="button"
                        onClick={() => setOpen(false)}
                        className="text-sidebar-foreground/60 lg:hidden"
                        aria-label="Close menu"
                    >
                        <X className="size-4" />
                    </button>
                </div>

                <nav className="flex flex-1 flex-col gap-0.5 overflow-y-auto">
                    {nav.map((item) => (
                        <NavLink
                            key={item.href}
                            href={item.href}
                            label={item.label}
                            icon={item.icon}
                            badge={item.badge}
                            active={isNavActive(path, item)}
                        />
                    ))}
                </nav>

                {!accountHref && (
                    <AccountMenu user={user} logoutHref={logoutHref} />
                )}
            </aside>

            {/* Tablet rail */}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-30 hidden w-18 flex-col items-center gap-1.5 bg-sidebar py-5 text-sidebar-foreground md:flex lg:hidden',
                    hideNav && 'md:hidden',
                )}
            >
                <Link
                    href={nav[0]?.href ?? '/'}
                    className="mb-4 flex size-9 items-center justify-center rounded-lg bg-sidebar-primary font-extrabold text-sidebar-primary-foreground"
                    aria-label="EMP"
                >
                    E
                </Link>
                {nav.map((item) => (
                    <RailLink
                        key={item.href}
                        href={item.href}
                        label={item.shortLabel ?? item.label}
                        icon={item.icon}
                        active={isNavActive(path, item)}
                    />
                ))}
                <div className="mt-auto">
                    {accountHref ? (
                        <Link
                            href={accountHref}
                            className="flex size-9 items-center justify-center rounded-full bg-card text-xs font-bold text-card-foreground"
                            aria-label="Account"
                        >
                            {initials(user.name)}
                        </Link>
                    ) : (
                        <AccountMenu
                            user={user}
                            logoutHref={logoutHref}
                            compact
                        />
                    )}
                </div>
            </aside>

            <div
                className={cn(
                    'min-w-0',
                    !hideNav && 'md:ml-18 lg:ml-58',
                    bottomBar &&
                        'pb-[calc(78px+env(safe-area-inset-bottom))] md:pb-0',
                )}
            >
                {header}
                <main className="min-w-0 px-3 py-4 md:px-6 md:py-6 lg:p-7">
                    {children}
                </main>
            </div>

            {bottomBar}

            <FlashMessages />
        </div>
    );
}

function Brand({ badge, inverse }: { badge?: string; inverse?: boolean }) {
    return (
        <div className="flex items-center gap-2 px-2">
            <div className="flex size-7 items-center justify-center rounded-md bg-primary text-[13px] font-extrabold text-primary-foreground">
                E
            </div>
            <span
                className={cn(
                    'font-bold',
                    inverse ? 'text-sidebar-foreground' : 'text-foreground',
                )}
            >
                EMP
            </span>
            {badge && (
                <span
                    className={cn(
                        'rounded px-1.5 py-0.5 text-[10px] font-bold tracking-[0.08em]',
                        inverse
                            ? 'bg-sidebar-foreground/12 text-sidebar-foreground'
                            : 'bg-foreground/10 text-foreground',
                    )}
                >
                    {badge}
                </span>
            )}
        </div>
    );
}

const APPEARANCES: { value: Appearance; label: string; icon: LucideIcon }[] = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];

/** Staff account block: avatar, name and role, opening theme + sign out. */
function AccountMenu({
    user,
    logoutHref,
    compact = false,
}: {
    user: DashboardShellProps['user'];
    logoutHref: string;
    compact?: boolean;
}) {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    'flex items-center gap-2.5 text-left outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring',
                    compact
                        ? 'rounded-full'
                        : 'w-full rounded-lg border-t border-sidebar-foreground/12 p-2 pt-4',
                )}
            >
                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-card text-xs font-bold text-card-foreground">
                    {initials(user.name)}
                </span>
                {!compact && (
                    <span className="min-w-0 text-[13px]">
                        <span className="block truncate font-semibold">
                            {user.name}
                        </span>
                        <span className="block truncate text-[11px] text-sidebar-foreground/70">
                            {user.subtitle}
                        </span>
                    </span>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent side="top" align="start" className="w-52">
                <DropdownMenuLabel>Appearance</DropdownMenuLabel>
                {APPEARANCES.map((option) => (
                    <DropdownMenuItem
                        key={option.value}
                        onClick={() => updateAppearance(option.value)}
                        className={cn(
                            appearance === option.value && 'font-semibold',
                        )}
                    >
                        <option.icon />
                        {option.label}
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    render={
                        <Link href={logoutHref} method="post" as="button" />
                    }
                    className="w-full"
                >
                    <LogOut />
                    Sign out
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
