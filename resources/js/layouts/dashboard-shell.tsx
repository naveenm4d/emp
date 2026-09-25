import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { LogOut, Menu, X, Zap } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import { FlashMessages } from '@/components/shared/flash-messages';
import { NavLink } from '@/components/shared/nav-link';
import { initials } from '@/lib/format';
import { cn } from '@/lib/utils';

export type NavItem = {
    label: string;
    href: string;
    icon: LucideIcon;
    /** Only highlight on this exact URL, not on nested pages. */
    exact?: boolean;
};

type DashboardShellProps = {
    product: string;
    nav: NavItem[];
    user: { name: string; subtitle: string };
    logoutHref: string;
    children: ReactNode;
};

/**
 * Sidebar + content frame shared by the client dashboard and the staff
 * console. Each area supplies its own navigation and account block.
 */
export function DashboardShell({
    product,
    nav,
    user,
    logoutHref,
    children,
}: DashboardShellProps) {
    const { url } = usePage();
    const [open, setOpen] = useState(false);
    const path = url.split('?')[0];

    const isActive = (item: NavItem) => {
        const target = new URL(item.href, 'http://x').pathname;

        return (
            path === target || (!item.exact && path.startsWith(`${target}/`))
        );
    };

    return (
        <div className="min-h-screen bg-background">
            {/* Mobile top bar */}
            <div className="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-border bg-background/95 px-4 backdrop-blur-sm lg:hidden">
                <Brand product={product} dark={false} />
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="rounded-md p-2 hover:bg-muted"
                    aria-label="Open menu"
                >
                    <Menu className="h-5 w-5" />
                </button>
            </div>

            {open && (
                <div
                    className="fixed inset-0 z-40 bg-black/40 lg:hidden"
                    onClick={() => setOpen(false)}
                />
            )}

            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-50 flex w-[232px] flex-col border-r border-white/[0.06] bg-[#0F0F13] transition-transform lg:translate-x-0',
                    open ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <div className="flex h-14 items-center justify-between border-b border-white/[0.06] px-4">
                    <Brand product={product} dark />
                    <button
                        type="button"
                        onClick={() => setOpen(false)}
                        className="text-white/50 lg:hidden"
                        aria-label="Close menu"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>

                <nav className="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">
                    {nav.map((item) => (
                        <NavLink
                            key={item.href}
                            href={item.href}
                            label={item.label}
                            icon={item.icon}
                            active={isActive(item)}
                        />
                    ))}
                </nav>

                <div className="border-t border-white/[0.06] p-3">
                    <div className="flex items-center gap-2.5 rounded-md px-2 py-2">
                        <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-500/20 text-[10px] font-semibold text-indigo-300">
                            {initials(user.name)}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-xs font-medium text-white/80">
                                {user.name}
                            </p>
                            <p className="truncate text-[10px] text-white/40">
                                {user.subtitle}
                            </p>
                        </div>
                        <Link
                            href={logoutHref}
                            method="post"
                            as="button"
                            className="text-white/30 transition-colors hover:text-white/70"
                            aria-label="Log out"
                        >
                            <LogOut
                                className="h-3.5 w-3.5"
                                strokeWidth={1.75}
                            />
                        </Link>
                    </div>
                </div>
            </aside>

            <main className="min-w-0 px-4 py-6 sm:px-6 lg:ml-[232px] lg:px-8">
                {children}
            </main>

            <FlashMessages />
        </div>
    );
}

function Brand({ product, dark }: { product: string; dark: boolean }) {
    return (
        <div className="flex items-center gap-2.5">
            <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-500/20">
                <Zap className="h-4 w-4 text-indigo-400" strokeWidth={2.5} />
            </div>
            <div>
                <p
                    className={cn(
                        'text-sm leading-none font-semibold tracking-tight',
                        dark ? 'text-white' : 'text-foreground',
                    )}
                >
                    EMP
                </p>
                <p
                    className={cn(
                        'mt-0.5 text-[10px] leading-none font-medium tracking-wide uppercase',
                        dark ? 'text-white/40' : 'text-muted-foreground',
                    )}
                >
                    {product}
                </p>
            </div>
        </div>
    );
}
