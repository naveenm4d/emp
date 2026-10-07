import { Link, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    House,
    Lock,
    MoreHorizontal,
    Search,
    UserCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';

import { EventPoster } from '@/components/events/event-poster';
import { EventSwitcher } from '@/components/events/event-switcher';
import { useEventTabs } from '@/components/events/event-tabs';
import { BottomTabBar } from '@/components/shared/bottom-tab-bar';
import { openCommandPalette } from '@/components/shared/command-palette';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import ClientLayout from '@/layouts/client-layout';
import { initials } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/client';
import { index as eventsIndex } from '@/routes/client/events';
import { edit as profileEdit } from '@/routes/client/profile';
import type { Event } from '@/types';

type EventLayoutProps = {
    event: Event;
    /** A line above the event's title on the poster (the overview's status). */
    headline?: string;
    children: ReactNode;
};

/**
 * Frame of every page that belongs to one event. From `md` up: the top bar
 * (home, switcher, search, account) and the event poster with the event's
 * tabs floating over its bottom edge, above every tab. On phones: the
 * event's own bottom tab bar.
 */
export default function EventLayout({
    event,
    headline,
    children,
}: EventLayoutProps) {
    return (
        <ClientLayout
            header={<EventHeader event={event} />}
            bottomBar={<EventTabBar event={event} />}
        >
            <EventPoster event={event} headline={headline} />
            {children}
        </ClientLayout>
    );
}

/**
 * The event's top bar from `md` up: home, the event switcher, search and
 * account (the tabs are on the poster).
 */
function EventHeader({ event }: { event: EventLayoutProps['event'] }) {
    const { auth } = usePage().props;
    return (
        <header className="sticky top-0 z-30 hidden md:block">
            <div className="flex h-14 items-center gap-3 bg-hero px-6 text-hero-foreground lg:px-7 dark:border-b dark:border-hero-edge">
                <Link
                    href={dashboard.url()}
                    aria-label="Home"
                    title="Home"
                    className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-hero-accent text-[13px] font-extrabold text-hero"
                >
                    E
                </Link>
                <Link
                    href={eventsIndex.url()}
                    className="hidden text-sm font-semibold text-hero-foreground/70 transition-colors hover:text-hero-foreground lg:inline"
                >
                    Events
                </Link>
                <ChevronRight
                    aria-hidden
                    className="hidden size-4 text-hero-foreground/40 lg:block"
                />
                <EventSwitcher
                    event={event}
                    className="h-9 max-w-72 bg-hero-foreground/8"
                />
                <div className="ml-auto flex items-center gap-2">
                    <button
                        type="button"
                        onClick={openCommandPalette}
                        className="hidden h-9 items-center gap-2 rounded-full bg-hero-foreground/8 pr-2 pl-3 text-sm text-hero-foreground/70 transition-colors hover:bg-hero-foreground/12 hover:text-hero-foreground lg:flex"
                    >
                        <Search className="size-4" />
                        Search
                        <kbd className="rounded-md bg-hero-foreground/10 px-1.5 py-0.5 text-[10px] font-semibold">
                            ⌘K
                        </kbd>
                    </button>
                    <button
                        type="button"
                        aria-label="Search"
                        onClick={openCommandPalette}
                        className="flex size-9 items-center justify-center rounded-full bg-hero-foreground/8 lg:hidden"
                    >
                        <Search className="size-4" />
                    </button>
                    <Link
                        href={profileEdit.url()}
                        aria-label="Account"
                        title="Account"
                        className="flex size-9 shrink-0 items-center justify-center rounded-full bg-card text-xs font-bold text-card-foreground"
                    >
                        {initials(auth.client?.name ?? '')}
                    </Link>
                </div>
            </div>
        </header>
    );
}

function EventTabBar({ event }: { event: EventLayoutProps['event'] }) {
    const tabs = useEventTabs(event);
    const [more, setMore] = useState(false);
    const primary = tabs.filter((tab) => tab.primary).slice(0, 4);
    const rest = tabs.filter((tab) => !primary.includes(tab));

    return (
        <>
            <BottomTabBar
                items={[
                    ...primary.map((tab) => ({
                        label: tab.label,
                        href: tab.href,
                        icon: tab.icon,
                        active: tab.active,
                        locked: tab.locked,
                    })),
                    {
                        label: 'More',
                        icon: MoreHorizontal,
                        active: rest.some((tab) => tab.active),
                        onClick: () => setMore(true),
                    },
                ]}
            />
            <Sheet open={more} onOpenChange={setMore}>
                <SheetContent>
                    <SheetTitle className="px-4 pt-3.5 pb-2 text-lg">
                        {event.title}
                    </SheetTitle>
                    <nav className="flex flex-col pb-7">
                        {rest.map((tab) => (
                            <Link
                                key={tab.href}
                                href={tab.href}
                                onClick={() => setMore(false)}
                                className={cn(
                                    'flex items-center gap-3 border-t border-border px-4 py-3.5 text-sm font-semibold',
                                    tab.active && 'bg-raised',
                                )}
                            >
                                <tab.icon
                                    className="size-5 text-muted-foreground"
                                    strokeWidth={1.75}
                                />
                                <span className="flex-1">{tab.label}</span>
                                {tab.locked && (
                                    <Lock
                                        aria-label="Upgrade to unlock"
                                        className="size-4 text-subtle"
                                    />
                                )}
                            </Link>
                        ))}
                        {[
                            {
                                label: 'Home',
                                href: dashboard.url(),
                                icon: House,
                            },
                            {
                                label: 'Account',
                                href: profileEdit.url(),
                                icon: UserCircle,
                            },
                        ].map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                onClick={() => setMore(false)}
                                className="flex items-center gap-3 border-t border-border px-4 py-3.5 text-sm font-semibold"
                            >
                                <item.icon
                                    className="size-5 text-muted-foreground"
                                    strokeWidth={1.75}
                                />
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                </SheetContent>
            </Sheet>
        </>
    );
}
