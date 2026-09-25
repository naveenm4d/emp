import { Head, useForm } from '@inertiajs/react';
import { CalendarDays, Clock, MapPin } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { FlashToast } from '@/components/site/flash-toast';
import { cn } from '@/lib/utils';
import type { EventPageProps, PublicEvent } from '@/types/site';

function formatDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatTime(event: PublicEvent): string | null {
    if (!event.start_time) {
        return null;
    }

    return event.end_time
        ? `${event.start_time} – ${event.end_time}`
        : event.start_time;
}

/** The public page of a published event, with self-registration. */
export default function ShowEvent({ event, actions }: EventPageProps) {
    const time = formatTime(event);

    return (
        <main className="bg-ivory text-ink min-h-screen px-5 py-16 sm:py-24">
            <Head title={event.title} />
            <FlashToast />

            <article className="mx-auto max-w-2xl">
                <header className="text-center">
                    {event.event_type && (
                        <p className="text-gold-deep text-xs tracking-[0.3em] uppercase">
                            {event.event_type}
                        </p>
                    )}
                    <h1 className="font-display mt-3 text-[clamp(2.4rem,7vw,4rem)] leading-[1.05] font-medium tracking-tight">
                        {event.title}
                    </h1>
                </header>

                <dl className="border-gold-soft/60 mt-10 grid gap-4 rounded-2xl border bg-white/60 p-6 sm:grid-cols-2">
                    {event.event_date && (
                        <Detail icon={<CalendarDays />} label="Date">
                            {formatDate(event.event_date)}
                        </Detail>
                    )}
                    {time && (
                        <Detail icon={<Clock />} label="Time">
                            {time}
                        </Detail>
                    )}
                    {(event.location_name || event.location_address) && (
                        <Detail
                            icon={<MapPin />}
                            label="Location"
                            className="sm:col-span-2"
                        >
                            {event.location_name && (
                                <span className="block">
                                    {event.location_name}
                                </span>
                            )}
                            {event.location_address && (
                                <span className="text-ink-soft/70 block">
                                    {event.location_address}
                                </span>
                            )}
                            {event.map_url && (
                                <a
                                    href={event.map_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-gold-deep mt-1 inline-block underline underline-offset-4"
                                >
                                    Open in maps
                                </a>
                            )}
                        </Detail>
                    )}
                </dl>

                {event.description && (
                    <p className="text-ink-soft mt-10 leading-relaxed whitespace-pre-line">
                        {event.description}
                    </p>
                )}

                <section className="bg-ink text-ivory mt-12 rounded-2xl p-6 sm:p-8">
                    {event.registration_open ? (
                        <RegistrationForm
                            event={event}
                            url={actions.register}
                        />
                    ) : (
                        <p className="text-ivory/80 text-center">
                            Registration for this event is closed.
                        </p>
                    )}
                </section>
            </article>
        </main>
    );
}

function Detail({
    icon,
    label,
    className,
    children,
}: {
    icon: ReactNode;
    label: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('flex gap-3', className)}>
            <span className="text-gold mt-0.5 [&>svg]:size-5" aria-hidden>
                {icon}
            </span>
            <div>
                <dt className="text-ink-soft/60 text-xs tracking-widest uppercase">
                    {label}
                </dt>
                <dd className="mt-1">{children}</dd>
            </div>
        </div>
    );
}

function RegistrationForm({ event, url }: { event: PublicEvent; url: string }) {
    const [registered, setRegistered] = useState(false);
    const form = useForm({ name: '', email: '', phone: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(url, {
            preserveScroll: true,
            onSuccess: (page) => {
                // Domain errors (full, closed, duplicate) come back as a flash error.
                if (!page.props.flash.error) {
                    setRegistered(true);
                    form.reset();
                }
            },
        });
    };

    if (registered) {
        return (
            <p className="font-display text-center text-2xl">
                {event.require_approval
                    ? "Thanks! We'll let you know once the host approves."
                    : "You're on the list. See you there!"}
            </p>
        );
    }

    return (
        <form onSubmit={submit} className="grid gap-4">
            <div className="text-center">
                <h2 className="font-display text-2xl">Join this event</h2>
                <p className="text-ivory/70 mt-1 text-sm">
                    Leave your email or phone so the host can reach you.
                    {event.require_approval &&
                        ' Registrations are approved by the host.'}
                </p>
            </div>

            <Field
                label="Name"
                error={form.errors.name}
                input={
                    <input
                        required
                        autoComplete="name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                }
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Email"
                    error={form.errors.email}
                    input={
                        <input
                            type="email"
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                    }
                />
                <Field
                    label="Phone"
                    error={form.errors.phone}
                    input={
                        <input
                            type="tel"
                            autoComplete="tel"
                            value={form.data.phone}
                            onChange={(e) =>
                                form.setData('phone', e.target.value)
                            }
                        />
                    }
                />
            </div>

            <button
                type="submit"
                disabled={form.processing}
                className="bg-gold text-ink hover:bg-gold-soft mt-2 rounded-full px-6 py-3 font-medium transition disabled:opacity-60"
            >
                {form.processing ? 'Registering…' : 'Register'}
            </button>
        </form>
    );
}

function Field({
    label,
    error,
    input,
}: {
    label: string;
    error?: string;
    input: ReactNode;
}) {
    return (
        <label className="grid gap-1.5 text-sm">
            <span className="text-ivory/80">{label}</span>
            <span className="[&>input]:border-ivory/20 [&>input]:bg-ivory/5 [&>input]:text-ivory [&>input]:focus:border-gold contents [&>input]:rounded-lg [&>input]:border [&>input]:px-3 [&>input]:py-2.5 [&>input]:outline-none">
                {input}
            </span>
            {error && <span className="text-red-300">{error}</span>}
        </label>
    );
}
