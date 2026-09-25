import { ArrowRight, Menu, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

import { navLinks } from './content';
import { useScrollY } from './hooks';

export function Logo({ className }: { className?: string }) {
    return (
        <a
            href="#top"
            className={cn('group flex items-center gap-2', className)}
            aria-label="EMP home"
        >
            <span className="bg-ink text-ivory relative grid size-8 place-items-center rounded-full transition-transform duration-500 group-hover:rotate-[20deg]">
                <svg aria-hidden viewBox="0 0 24 24" className="size-4">
                    <path
                        d="M3 7.5 12 13l9-5.5M4.5 5h15A1.5 1.5 0 0 1 21 6.5v11a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5v-11A1.5 1.5 0 0 1 4.5 5Z"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.6"
                        strokeLinejoin="round"
                    />
                </svg>
                <span className="bg-gold ring-ivory absolute -top-0.5 -right-0.5 size-2.5 rounded-full ring-2" />
            </span>
            <span className="font-display text-xl font-semibold tracking-tight">
                emp
            </span>
        </a>
    );
}

export function Nav({ dashboardUrl }: { dashboardUrl: string }) {
    const scrolled = useScrollY() > 24;
    const [open, setOpen] = useState(false);

    useEffect(() => {
        document.body.style.overflow = open ? 'hidden' : '';
        const onKey = (e: KeyboardEvent) =>
            e.key === 'Escape' && setOpen(false);
        window.addEventListener('keydown', onKey);

        return () => {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', onKey);
        };
    }, [open]);

    return (
        <header className="fixed inset-x-0 top-0 z-50 px-3 pt-3 sm:px-6">
            <nav
                aria-label="Main"
                className={cn(
                    'mx-auto flex h-14 max-w-6xl items-center justify-between rounded-full px-4 transition-all duration-500 sm:px-5',
                    scrolled
                        ? 'border-ink/10 bg-ivory/85 border shadow-[0_10px_40px_-20px_rgb(22_19_15/0.35)] backdrop-blur-xl backdrop-saturate-150'
                        : 'border border-transparent',
                )}
            >
                <Logo />

                <ul className="hidden items-center gap-1 md:flex">
                    {navLinks.map((link) => (
                        <li key={link.href}>
                            <a
                                href={link.href}
                                className="text-ink/70 hover:bg-ink/5 hover:text-ink rounded-full px-3.5 py-2 text-sm transition-colors"
                            >
                                {link.label}
                            </a>
                        </li>
                    ))}
                </ul>

                <div className="hidden items-center gap-2 md:flex">
                    <a
                        href={dashboardUrl}
                        className="text-ink/70 hover:text-ink px-3 py-2 text-sm transition-colors"
                    >
                        Sign in
                    </a>
                    <a
                        href={dashboardUrl}
                        className="group bg-ink text-ivory hover:bg-gold-deep inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition-all"
                    >
                        Get started
                        <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" />
                    </a>
                </div>

                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="grid size-10 place-items-center rounded-full md:hidden"
                    aria-label="Open menu"
                    aria-expanded={open}
                    aria-controls="mobile-menu"
                >
                    <Menu className="size-5" />
                </button>
            </nav>

            <div
                id="mobile-menu"
                className={cn(
                    'bg-ivory/95 fixed inset-0 z-50 flex flex-col px-6 pt-5 pb-10 backdrop-blur-2xl transition-all duration-500 md:hidden',
                    open ? 'visible opacity-100' : 'invisible opacity-0',
                )}
                aria-hidden={!open}
            >
                <div className="flex items-center justify-between">
                    <Logo />
                    <button
                        type="button"
                        onClick={() => setOpen(false)}
                        className="grid size-10 place-items-center rounded-full"
                        aria-label="Close menu"
                        tabIndex={open ? 0 : -1}
                    >
                        <X className="size-5" />
                    </button>
                </div>
                <ul className="mt-12 space-y-1">
                    {navLinks.map((link, i) => (
                        <li
                            key={link.href}
                            className={cn(
                                'transition-all duration-500',
                                open
                                    ? 'translate-y-0 opacity-100'
                                    : 'translate-y-4 opacity-0',
                            )}
                            style={{
                                transitionDelay: open
                                    ? `${100 + i * 60}ms`
                                    : '0ms',
                            }}
                        >
                            <a
                                href={link.href}
                                onClick={() => setOpen(false)}
                                tabIndex={open ? 0 : -1}
                                className="font-display block py-2 text-4xl tracking-tight"
                            >
                                {link.label}
                            </a>
                        </li>
                    ))}
                </ul>
                <div className="mt-auto grid gap-3">
                    <a
                        href={dashboardUrl}
                        tabIndex={open ? 0 : -1}
                        className="bg-ink text-ivory rounded-full py-4 text-center font-medium"
                    >
                        Get started
                    </a>
                    <a
                        href={dashboardUrl}
                        tabIndex={open ? 0 : -1}
                        className="border-ink/15 rounded-full border py-4 text-center font-medium"
                    >
                        Sign in
                    </a>
                </div>
            </div>
        </header>
    );
}
