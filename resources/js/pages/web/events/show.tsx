import { Head } from '@inertiajs/react';
import { useState } from 'react';

import { FlashToast } from '@/components/web/flash-toast';
import { BackgroundMusic } from '@/components/web/invitation/background-music';
import { InvitationFrame } from '@/components/web/invitation/invitation-frame';
import { RegistrationDialog } from '@/components/web/invitation/registration-dialog';
import { rsvpSubtitle } from '@/components/web/invitation/rsvp-actions';
import { useFonts } from '@/components/web/invitation/use-fonts';
import type { EventPageProps } from '@/types/web';

/**
 * The public event page: the event's invitation design (the same one guests
 * get). While registration is open, "Register to attend" opens the EMP
 * registration popup with the details the event asks for.
 */
export default function ShowEvent({
    event,
    design,
    form,
    actions,
}: EventPageProps) {
    const [root, setRoot] = useState<ShadowRoot | null>(null);
    const [open, setOpen] = useState(false);
    const [registered, setRegistered] = useState(false);
    useFonts(design.fonts);

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
                        {registered ? (
                            <p className="emp-rsvp__message" role="status">
                                {event.requires_approval
                                    ? "Thanks! We'll let you know once the host approves."
                                    : "You're on the list. See you there!"}
                            </p>
                        ) : event.registration_open ? (
                            <div className="emp-rsvp__actions">
                                <button
                                    type="button"
                                    className="emp-rsvp__button emp-rsvp__button--accept"
                                    onClick={() => setOpen(true)}
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
                <RegistrationDialog
                    open={open}
                    onOpenChange={setOpen}
                    mode="register"
                    form={form}
                    url={actions.register}
                    subtitle={rsvpSubtitle(event)}
                    note={
                        event.requires_approval
                            ? 'Registrations are approved by the host.'
                            : 'Leave your details so the host can reach you.'
                    }
                    onSaved={() => setRegistered(true)}
                />
            )}
        </>
    );
}
