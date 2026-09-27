import { useState } from 'react';

import { RegistrationDialog } from '@/components/web/invitation/registration-dialog';
import type { Attendance } from '@/components/web/invitation/registration-dialog';
import { formatDayMonth } from '@/lib/format';
import type {
    InvitationPageProps,
    PublicEvent,
    RegistrationForm,
    RegistrationResponse,
    Rsvp,
} from '@/types/web';

type RsvpActionsProps = Pick<
    InvitationPageProps,
    'mode' | 'rsvp' | 'actions'
> & {
    /** What the event asks for; without it (design editor) the buttons are inert. */
    form?: RegistrationForm | null;
    response?: RegistrationResponse | null;
    /** For the popup's header ("Nimali & Kasun · 14 Nov"). */
    event?: Pick<PublicEvent, 'title' | 'event_date'> | null;
};

type State =
    | 'awaiting'
    | 'accepted'
    | 'declined'
    | 'maybe'
    | 'expired'
    | 'preview';

function stateOf(mode: string, rsvp: Rsvp | null): State {
    if (mode === 'preview' || !rsvp) {
        return 'preview';
    }

    if (
        rsvp.status === 'accepted' ||
        rsvp.status === 'declined' ||
        rsvp.status === 'maybe'
    ) {
        return rsvp.status;
    }

    if (rsvp.is_expired || rsvp.status === 'expired') {
        return 'expired';
    }

    return 'awaiting';
}

const messages: Record<State, string> = {
    awaiting: 'Will you be joining us?',
    accepted: "You're attending. We can't wait to see you!",
    declined: "You've let us know you can't make it. You'll be missed.",
    maybe: "You might be joining us. Let us know when you're sure!",
    expired: 'This invitation is no longer accepting responses.',
    preview: 'Will you be joining us?',
};

/** "Nimali & Kasun · 14 Nov" for the popup's header. */
export function rsvpSubtitle(
    event?: { title: string; event_date: string | null } | null,
): string {
    if (!event) {
        return 'Your invitation';
    }

    return event.event_date
        ? `${event.title} · ${formatDayMonth(event.event_date)}`
        : event.title;
}

/**
 * The one RSVP button templates show (placed by `{{ rsvp }}`). Rendered
 * inside the invitation's shadow root, so it uses plain class names
 * (.emp-rsvp…) that templates can style. It opens the EMP RSVP popup (2b),
 * where the guest says whether they're coming and gives the details.
 */
export function RsvpActions({
    mode,
    rsvp,
    actions,
    form,
    response,
    event,
}: RsvpActionsProps) {
    const [open, setOpen] = useState(false);
    const state = stateOf(mode, rsvp);
    const preview = state === 'preview';
    const answered =
        state === 'accepted' || state === 'declined' || state === 'maybe';
    // In the design editor there is no form to preview, so the button stays inert.
    const inert = preview && !form;

    return (
        <div className="emp-rsvp" data-state={state}>
            {answered && (
                <p className="emp-rsvp__message" role="status">
                    {messages[state]}
                </p>
            )}
            {state === 'expired' ? (
                <p className="emp-rsvp__message" role="status">
                    {messages.expired}
                </p>
            ) : (
                (!answered || rsvp?.can_change) && (
                    <div className="emp-rsvp__actions">
                        <button
                            type="button"
                            className={
                                answered
                                    ? 'emp-rsvp__button emp-rsvp__button--change'
                                    : 'emp-rsvp__button emp-rsvp__button--accept'
                            }
                            disabled={inert}
                            onClick={() => setOpen(true)}
                        >
                            {answered ? 'Change response' : 'RSVP'}
                        </button>
                    </div>
                )
            )}
            {preview && (
                <p className="emp-rsvp__note">
                    Preview: guests will be able to respond here.
                </p>
            )}
            {form && (
                <RegistrationDialog
                    // Starts fresh each time it opens.
                    key={open ? 'open' : 'closed'}
                    open={open}
                    onOpenChange={setOpen}
                    mode={preview ? 'preview' : 'rsvp'}
                    form={form}
                    url={preview ? null : (actions?.respond ?? null)}
                    subtitle={rsvpSubtitle(event)}
                    initial={response}
                    initialAttendance={answered ? (state as Attendance) : null}
                />
            )}
        </div>
    );
}
