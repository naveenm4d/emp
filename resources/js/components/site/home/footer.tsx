import { navLinks } from './content';
import { Logo } from './nav';

export function Footer({ dashboardUrl }: { dashboardUrl: string }) {
    const columns = [
        {
            title: 'Product',
            links: navLinks.map((l) => ({ href: l.href, label: l.label })),
        },
        {
            title: 'Account',
            links: [
                { href: dashboardUrl, label: 'Sign in' },
                { href: dashboardUrl, label: 'Create an event' },
            ],
        },
        {
            title: 'Occasions',
            links: [
                { href: '#templates', label: 'Weddings' },
                { href: '#templates', label: 'Birthdays' },
                { href: '#templates', label: 'Corporate events' },
            ],
        },
    ];

    return (
        <footer className="mx-auto max-w-6xl px-6 pt-20 pb-10">
            <div className="grid gap-12 md:grid-cols-[1.5fr_repeat(3,1fr)]">
                <div>
                    <Logo />
                    <p className="text-ink/55 mt-4 max-w-xs text-sm leading-relaxed">
                        Beautiful digital invitations, delivered on WhatsApp,
                        with RSVPs you can count on.
                    </p>
                </div>
                {columns.map((col) => (
                    <nav key={col.title} aria-label={col.title}>
                        <p className="text-sm font-semibold">{col.title}</p>
                        <ul className="text-ink/55 mt-4 space-y-2.5 text-sm">
                            {col.links.map((link) => (
                                <li key={link.label}>
                                    <a
                                        href={link.href}
                                        className="hover:text-ink transition-colors"
                                    >
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </nav>
                ))}
            </div>
            <div className="border-ink/10 text-ink/45 mt-16 flex flex-col items-center justify-between gap-4 border-t pt-8 text-xs sm:flex-row">
                <p>© {new Date().getFullYear()} EMP. All rights reserved.</p>
                <p className="font-display text-sm italic">
                    Made for every celebration.
                </p>
            </div>
        </footer>
    );
}
