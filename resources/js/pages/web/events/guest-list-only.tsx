import { Head } from '@inertiajs/react';
import { CalendarDays, MailOpen, MapPin } from 'lucide-react';

import { formatDate } from '@/lib/format';
import type { PublicEvent } from '@/types/web';

/**
 * The public URL of a guest-list-only event: there is no public invitation or
 * registration, so guests are pointed to the personal link they received.
 */
export default function GuestListOnly({ event }: { event: PublicEvent }) {
    const when = [
        event.event_date ? formatDate(event.event_date) : null,
        event.start_time,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <>
            <Head title={event.title} />
            <main className="bg-ink text-ivory flex min-h-dvh items-center justify-center px-5 py-16">
                <div className="w-full max-w-md text-center">
                    <div className="bg-gold/15 text-gold mx-auto mb-6 flex size-14 items-center justify-center rounded-full">
                        <MailOpen className="size-7" />
                    </div>
                    <p className="text-gold text-xs font-medium tracking-[0.2em] uppercase">
                        Invited guests only
                    </p>
                    <h1 className="font-display mt-3 text-3xl sm:text-4xl">
                        {event.title}
                    </h1>

                    {(when || event.location_name) && (
                        <div className="text-ivory/70 mt-4 flex flex-col items-center gap-1.5 text-sm">
                            {when && (
                                <span className="inline-flex items-center gap-1.5">
                                    <CalendarDays className="size-4" />
                                    {when}
                                </span>
                            )}
                            {event.location_name && (
                                <span className="inline-flex items-center gap-1.5">
                                    <MapPin className="size-4" />
                                    {event.location_name}
                                </span>
                            )}
                        </div>
                    )}

                    <div className="border-ivory/15 bg-ivory/5 mt-8 rounded-2xl border p-6 text-left">
                        <p className="font-medium">
                            This event is only for guests on the host's guest
                            list.
                        </p>
                        <p className="text-ivory/70 mt-2 text-sm leading-relaxed">
                            If you're invited, open the personal link you
                            received. It shows your invitation and lets you
                            RSVP.
                        </p>
                        <p className="text-ivory/50 mt-4 text-xs">
                            Can't find your link? Ask the host to send it again.
                        </p>
                    </div>
                </div>
            </main>
        </>
    );
}
