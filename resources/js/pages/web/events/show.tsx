import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { FlashToast } from '@/components/web/flash-toast';
import { BackgroundMusic } from '@/components/web/invitation/background-music';
import { InvitationFrame } from '@/components/web/invitation/invitation-frame';
import { useFonts } from '@/components/web/invitation/use-fonts';
import type { EventPageProps, PublicEvent } from '@/types/web';

/** The public page of a published event, with self-registration. */
/**
 * The public event page: the event's invitation design (the same one guests
 * get), with self-registration below it when registration is open.
 */
export default function ShowEvent({ event, design, actions }: EventPageProps) {
    const [root, setRoot] = useState<ShadowRoot | null>(null);
    useFonts(design.fonts);

    const scrollToRegistration = () =>
        document
            .getElementById('register')
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });

    return (
        <>
            <Head title={event.title} />
            <FlashToast />
            <InvitationFrame
                html={design.html}
                scripts={design.scripts}
                onReady={setRoot}
                rsvp={
                    <div
                        className="emp-rsvp"
                        data-state={event.registration_open ? 'open' : 'closed'}
                    >
                        {event.registration_open ? (
                            <div className="emp-rsvp__actions">
                                <button
                                    type="button"
                                    className="emp-rsvp__button emp-rsvp__button--accept"
                                    onClick={scrollToRegistration}
                                >
                                    Register to attend
                                </button>
                            </div>
                        ) : (
                            <p className="emp-rsvp__message">
                                Registration for this event is closed.
                            </p>
                        )}
                    </div>
                }
            />
            {design.has_bg_music && <BackgroundMusic root={root} />}

            {event.registration_open && (
                <section
                    id="register"
                    className="bg-ink text-ivory px-5 py-12 sm:py-16"
                >
                    <div className="mx-auto max-w-2xl">
                        <RegistrationForm
                            event={event}
                            url={actions.register}
                        />
                    </div>
                </section>
            )}
        </>
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
                {event.requires_approval
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
                    {event.requires_approval &&
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
