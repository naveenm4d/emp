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
import { Fragment, useState } from 'react';

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
    event: Pick<Event, 'id' | 'title'>;
    children: ReactNode;
};

/**
 * Frame of every page that belongs to one event (4b): the event header with
 * the switcher and tabs from `md` up, the event's own tab bar on phones.
 */
export default function EventLayout({ event, children }: EventLayoutProps) {
    return (
        <ClientLayout
            header={<EventHeader event={event} />}
            bottomBar={<EventTabBar event={event} />}
        >
            {children}
        </ClientLayout>
    );
}

/** Tabs that belong together sit between dividers on the desktop tab strip. */
const TAB_GROUPS: Record<string, number> = {
    Overview: 0,
    Guests: 1,
    RSVPs: 1,
    'RSVP form': 1,
    Messages: 1,
    Design: 2,
    Seating: 2,
    Settings: 3,
};

/**
 * The event's header from `md` up: a top bar (home, the event switcher,
 * search and account) over a floating strip of the event's pages.
 */
function EventHeader({ event }: { event: EventLayoutProps['event'] }) {
    const { auth } = usePage().props;
    const tabs = useEventTabs(event);

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

            <div className="border-b border-border bg-background/85 px-6 py-2.5 backdrop-blur lg:px-7">
                <nav
                    aria-label="Event pages"
                    className="flex w-fit max-w-full [scrollbar-width:none] items-center gap-1 overflow-x-auto rounded-full bg-card p-1 shadow-card"
                >
                    {tabs.map((tab, index) => {
                        const Icon = tab.icon;
                        const newGroup =
                            index > 0 &&
                            TAB_GROUPS[tab.label] !==
                                TAB_GROUPS[tabs[index - 1].label];

                        return (
                            <Fragment key={tab.href}>
                                {newGroup && (
                                    <span
                                        aria-hidden
                                        className="mx-1 h-5 w-px shrink-0 bg-border"
                                    />
                                )}
                                <Link
                                    href={tab.href}
                                    aria-current={
                                        tab.active ? 'page' : undefined
                                    }
                                    title={
                                        tab.locked
                                            ? `${tab.label}: upgrade to unlock`
                                            : undefined
                                    }
                                    className={cn(
                                        'flex h-9 shrink-0 items-center gap-2 rounded-full px-3.5 text-sm font-semibold whitespace-nowrap transition-colors',
                                        tab.active
                                            ? 'bg-strong text-strong-foreground shadow-sm'
                                            : 'text-muted-foreground hover:bg-raised hover:text-foreground',
                                        tab.locked &&
                                            !tab.active &&
                                            'text-subtle',
                                    )}
                                >
                                    <Icon
                                        className="size-4"
                                        strokeWidth={tab.active ? 2.25 : 1.9}
                                    />
                                    {tab.label}
                                    {tab.locked && (
                                        <Lock
                                            aria-label="Upgrade to unlock"
                                            className="size-3 opacity-70"
                                        />
                                    )}
                                </Link>
                            </Fragment>
                        );
                    })}
                </nav>
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
