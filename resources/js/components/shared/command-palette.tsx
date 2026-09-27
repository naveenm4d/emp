import { router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CalendarDays,
    CornerDownLeft,
    House,
    LayoutTemplate,
    Plus,
    Search,
    UserCircle,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { formatShortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/client';
import { create, index as eventsIndex, show } from '@/routes/client/events';
import { index as guestsIndex } from '@/routes/client/events/guests';
import { edit as profileEdit } from '@/routes/client/profile';
import { index as templatesIndex } from '@/routes/client/templates';

/** Fired by search buttons to open the palette. */
export const OPEN_COMMAND_PALETTE = 'emp:command-palette';

export function openCommandPalette() {
    window.dispatchEvent(new Event(OPEN_COMMAND_PALETTE));
}

type Item = {
    key: string;
    group: 'Actions' | 'Events' | 'Go to';
    icon: LucideIcon;
    label: React.ReactNode;
    detail?: string;
    href: string;
};

/**
 * ⌘K / Ctrl+K search over the client's events and pages (4c): jump to an
 * event, search guests of the event you're on, or search all events.
 */
export function CommandPalette() {
    const page = usePage();
    const events = page.props.eventSwitcher;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const list = useRef<HTMLDivElement>(null);

    const currentEvent = useMemo(() => {
        const id = page.url.match(/^\/app\/events\/([0-9a-f-]{36})/)?.[1];

        return id
            ? (events?.find((event) => event.id === id) ?? {
                  id,
                  title: 'this event',
              })
            : null;
    }, [page.url, events]);

    useEffect(() => {
        const show = () => {
            setQuery('');
            setActive(0);
            setOpen(true);
            router.reload({ only: ['eventSwitcher'] });
        };
        const onKey = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                show();
            }
        };

        window.addEventListener('keydown', onKey);
        window.addEventListener(OPEN_COMMAND_PALETTE, show);

        return () => {
            window.removeEventListener('keydown', onKey);
            window.removeEventListener(OPEN_COMMAND_PALETTE, show);
        };
    }, []);

    const items = useMemo<Item[]>(() => {
        const q = query.trim().toLowerCase();
        const actions: Item[] = q
            ? [
                  ...(currentEvent
                      ? [
                            {
                                key: 'guests',
                                group: 'Actions' as const,
                                icon: Users,
                                label: (
                                    <>
                                        Search guests for{' '}
                                        <b>“{query.trim()}”</b> in{' '}
                                        {currentEvent.title}
                                    </>
                                ),
                                href: guestsIndex.url(currentEvent.id, {
                                    query: { search: query.trim() },
                                }),
                            },
                        ]
                      : []),
                  {
                      key: 'events-search',
                      group: 'Actions',
                      icon: Search,
                      label: (
                          <>
                              Search events for <b>“{query.trim()}”</b>
                          </>
                      ),
                      href: eventsIndex.url({
                          query: { search: query.trim(), period: 'all' },
                      }),
                  },
              ]
            : [];
        const matching: Item[] = (events ?? [])
            .filter((event) => !q || event.title.toLowerCase().includes(q))
            .slice(0, 6)
            .map((event) => ({
                key: event.id,
                group: 'Events',
                icon: CalendarDays,
                label: event.title,
                detail: formatShortDate(event.event_date),
                href: show.url(event.id),
            }));
        const pages: Item[] = [
            { key: 'home', label: 'Home', icon: House, href: dashboard.url() },
            {
                key: 'events',
                label: 'Events',
                icon: CalendarDays,
                href: eventsIndex.url(),
            },
            { key: 'new', label: 'New event', icon: Plus, href: create.url() },
            {
                key: 'templates',
                label: 'Templates',
                icon: LayoutTemplate,
                href: templatesIndex.url(),
            },
            {
                key: 'account',
                label: 'Account',
                icon: UserCircle,
                href: profileEdit.url(),
            },
        ]
            .filter((item) => !q || item.label.toLowerCase().includes(q))
            .map((item) => ({ ...item, group: 'Go to' as const }));

        return [...actions, ...matching, ...pages];
    }, [query, events, currentEvent]);

    const go = (item: Item | undefined) => {
        if (item) {
            setOpen(false);
            router.visit(item.href);
        }
    };

    useEffect(() => {
        list.current
            ?.querySelector(`[data-index="${active}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [active]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="top-26 max-w-2xl translate-y-0 [&>button[aria-label=Close]]:hidden">
                <DialogTitle className="sr-only">Search</DialogTitle>
                <div className="flex items-center gap-3 border-b border-border px-5 py-4">
                    <Search className="size-5 shrink-0 text-muted-foreground" />
                    <input
                        autoFocus
                        value={query}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setActive(0);
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'ArrowDown') {
                                e.preventDefault();
                                setActive((i) =>
                                    Math.min(i + 1, items.length - 1),
                                );
                            } else if (e.key === 'ArrowUp') {
                                e.preventDefault();
                                setActive((i) => Math.max(i - 1, 0));
                            } else if (e.key === 'Enter') {
                                e.preventDefault();
                                go(items[active]);
                            }
                        }}
                        placeholder="Find an event, guest or page"
                        className="min-w-0 flex-1 bg-transparent text-lg outline-none placeholder:text-subtle"
                    />
                    <kbd className="rounded border border-input px-1.5 py-0.5 text-[11px] text-muted-foreground">
                        esc
                    </kbd>
                </div>
                <div
                    ref={list}
                    className="max-h-[50dvh] overflow-y-auto px-2 py-2"
                >
                    {items.length === 0 && (
                        <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                            Nothing matches “{query}”.
                        </p>
                    )}
                    {items.map((item, index) => (
                        <div key={item.key}>
                            {item.group !== items[index - 1]?.group && (
                                <div className="px-3 pt-2 pb-1.5 text-[11px] font-bold tracking-[0.08em] text-subtle uppercase">
                                    {item.group}
                                </div>
                            )}
                            <button
                                type="button"
                                data-index={index}
                                onMouseMove={() => setActive(index)}
                                onClick={() => go(item)}
                                className={cn(
                                    'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[15px]',
                                    index === active &&
                                        'bg-raised shadow-[inset_3px_0_0_var(--primary)]',
                                )}
                            >
                                <item.icon
                                    className="size-4.5 shrink-0 text-muted-foreground"
                                    strokeWidth={1.75}
                                />
                                <span className="min-w-0 flex-1 truncate">
                                    {item.label}
                                </span>
                                {item.detail && (
                                    <span className="text-sm text-muted-foreground">
                                        {item.detail}
                                    </span>
                                )}
                                {index === active && (
                                    <CornerDownLeft className="size-4 text-muted-foreground" />
                                )}
                            </button>
                        </div>
                    ))}
                </div>
                <div className="flex gap-4 border-t border-border px-5 py-2.5 text-xs text-muted-foreground">
                    <span>↑↓ move</span>
                    <span>↵ open</span>
                </div>
            </DialogContent>
        </Dialog>
    );
}
