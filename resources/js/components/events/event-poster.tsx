import { Link } from '@inertiajs/react';
import { CalendarDays, Clock, Lock, MapPin, Palette } from 'lucide-react';
import { Fragment } from 'react';

import { EventPills } from '@/components/events/event-pills';
import { useEventTabs } from '@/components/events/event-tabs';
import { formatShortDate, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { design } from '@/routes/client/events';
import type { Event } from '@/types';

/** Tabs that belong together sit between dividers. */
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
 * The top of every event page from `md` up, invitation first: the
 * invitation's image fills the header with the event over it, and the
 * event's pages float as a tab bar over its bottom edge.
 */
export function EventPoster({
    event,
    headline,
}: {
    event: Event;
    /** A line above the title (the overview's "12 parties haven't replied yet"). */
    headline?: string;
}) {
    const tabs = useEventTabs(event);
    const thumbnail = event.template?.thumbnail_url;

    return (
        <section className="relative mb-12 hidden md:block">
            <div className="relative isolate h-96 overflow-hidden rounded-[2rem] bg-hero text-white shadow-lg lg:h-96 xl:h-[23rem]">
                {thumbnail ? (
                    <>
                        {/* A blurred copy fills the width; the image itself sits on the right. */}
                        <img
                            src={thumbnail}
                            alt=""
                            aria-hidden
                            className="absolute inset-0 -z-20 size-full scale-110 object-cover blur-2xl"
                        />
                        <img
                            src={thumbnail}
                            alt=""
                            aria-hidden
                            className="absolute inset-y-0 right-0 -z-20 h-full w-3/5 [mask-image:linear-gradient(to_right,transparent,black_35%)] object-cover object-top"
                        />
                    </>
                ) : (
                    <div
                        aria-hidden
                        className="absolute -top-24 -right-16 -z-20 size-[30rem] rounded-full bg-hero-glow/40 blur-3xl"
                    />
                )}
                <div
                    aria-hidden
                    className="absolute inset-0 -z-10 bg-linear-to-r from-black/80 via-black/55 to-black/10"
                />
                <div
                    aria-hidden
                    className="absolute inset-0 -z-10 bg-linear-to-t from-black/70 via-transparent to-black/30"
                />

                <div className="flex h-full flex-col justify-between p-8 pb-12 lg:p-10 lg:pb-14">
                    <div className="flex items-start justify-between gap-3">
                        <EventPills event={event} className="min-w-0" />
                        <Link
                            href={design.url(event.id)}
                            className="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-full bg-white/15 px-3 text-xs font-semibold ring-1 ring-white/25 backdrop-blur-md transition-colors hover:bg-white/25"
                        >
                            <Palette className="size-3.5" /> Edit invitation
                        </Link>
                    </div>

                    <div>
                        <div className="max-w-3xl min-w-0">
                            {headline && (
                                <p className="text-sm font-semibold text-white/75">
                                    {headline}
                                </p>
                            )}
                            <h1 className="mt-1.5 text-4xl leading-[1.05] font-bold tracking-tight text-balance drop-shadow-sm lg:text-5xl">
                                {event.title}
                            </h1>
                            <div className="mt-4 flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-white/85">
                                {event.event_date && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <CalendarDays className="size-4" />
                                        {formatShortDate(
                                            event.event_date,
                                            true,
                                        )}
                                    </span>
                                )}
                                {event.start_time && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <Clock className="size-4" />
                                        {formatTime(event.start_time)}
                                        {event.end_time &&
                                            ` – ${formatTime(event.end_time)}`}
                                    </span>
                                )}
                                {event.location_name && (
                                    <span className="inline-flex min-w-0 items-center gap-1.5">
                                        <MapPin className="size-4 shrink-0" />
                                        <span className="truncate">
                                            {event.location_name}
                                        </span>
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <nav
                aria-label="Event pages"
                className="absolute inset-x-6 bottom-0 flex translate-y-1/2 justify-center lg:inset-x-10"
            >
                <div className="flex max-w-full [scrollbar-width:none] items-center gap-1 overflow-x-auto rounded-full bg-card/85 p-1.5 shadow-xl ring-1 ring-border backdrop-blur-xl">
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
                                        className="mx-0.5 h-5 w-px shrink-0 bg-border"
                                    />
                                )}
                                <Link
                                    href={tab.href}
                                    preserveScroll
                                    aria-current={
                                        tab.active ? 'page' : undefined
                                    }
                                    title={
                                        tab.locked
                                            ? `${tab.label}: upgrade to unlock`
                                            : undefined
                                    }
                                    className={cn(
                                        'flex h-10 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-semibold whitespace-nowrap transition-colors',
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
                                    <span className="hidden lg:inline">
                                        {tab.label}
                                    </span>
                                    <span className="lg:hidden">
                                        {tab.active ? tab.label : null}
                                    </span>
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
                </div>
            </nav>
        </section>
    );
}
