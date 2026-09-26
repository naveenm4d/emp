import { router } from '@inertiajs/react';
import { useState } from 'react';

import { DeclineDialog } from '@/components/web/invitation/decline-dialog';
import {
    hasRegistrationDetails,
    RegistrationDialog,
} from '@/components/web/invitation/registration-dialog';
import type {
    InvitationPageProps,
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
};

type State =
    | 'awaiting'
    | 'accepted'
    | 'declined'
    | 'maybe'
    | 'expired'
    | 'preview';

type Answer = 'accepted' | 'declined' | 'maybe';

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

/**
 * Accept / maybe / decline. Rendered inside the invitation's shadow root, so
 * it uses plain class names (.emp-rsvp…) that templates can style. When the
 * event asks for details, accepting opens the EMP registration popup;
 * declining asks for confirmation, with an optional note to the host.
 */
export function RsvpActions({
    mode,
    rsvp,
    actions,
    form,
    response,
}: RsvpActionsProps) {
    const [processing, setProcessing] = useState(false);
    const [changing, setChanging] = useState(false);
    const [dialog, setDialog] = useState<'accepted' | 'maybe' | null>(null);
    const [declining, setDeclining] = useState(false);
    const state = stateOf(mode, rsvp);
    const preview = state === 'preview';
    const asksDetails = form ? hasRegistrationDetails(form) : false;

    const post = (attendance: Answer) =>
        actions &&
        router.post(
            actions.respond,
            { attendance },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setChanging(false),
            },
        );

    const answer = (attendance: Answer) => {
        if (attendance === 'declined') {
            // Confirmed in a dialog, with an optional note to the host.
            setDeclining(true);
        } else if (asksDetails) {
            setDialog(attendance);
        } else if (!preview) {
            post(attendance);
        }
    };

    const showButtons = state === 'awaiting' || preview || changing;
    // In the design editor there is no form to preview, so the buttons stay inert.
    const inert = (preview && !form) || processing;

    return (
        <div className="emp-rsvp" data-state={changing ? 'awaiting' : state}>
            <p className="emp-rsvp__message" role="status">
                {changing ? messages.awaiting : messages[state]}
            </p>
            {showButtons && (
                <div className="emp-rsvp__actions">
                    <button
                        type="button"
                        className="emp-rsvp__button emp-rsvp__button--accept"
                        disabled={inert}
                        onClick={() => answer('accepted')}
                    >
                        Accept
                    </button>
                    {form?.allow_maybe && (
                        <button
                            type="button"
                            className="emp-rsvp__button emp-rsvp__button--maybe"
                            disabled={inert}
                            onClick={() => answer('maybe')}
                        >
                            Maybe
                        </button>
                    )}
                    <button
                        type="button"
                        className="emp-rsvp__button emp-rsvp__button--decline"
                        disabled={inert}
                        onClick={() => answer('declined')}
                    >
                        Decline
                    </button>
                </div>
            )}
            {!showButtons && rsvp?.can_change && (
                <div className="emp-rsvp__actions">
                    <button
                        type="button"
                        className="emp-rsvp__button emp-rsvp__button--change"
                        onClick={() => setChanging(true)}
                    >
                        Change response
                    </button>
                </div>
            )}
            {preview && (
                <p className="emp-rsvp__note">
                    Preview: guests will be able to respond here.
                </p>
            )}
            {form && (
                <RegistrationDialog
                    open={dialog !== null}
                    onOpenChange={(open) => !open && setDialog(null)}
                    mode={preview ? 'preview' : 'rsvp'}
                    form={form}
                    url={actions?.respond ?? null}
                    attendance={dialog ?? 'accepted'}
                    initial={response}
                    title={
                        dialog === 'maybe'
                            ? 'You might be coming'
                            : "Great, you're coming!"
                    }
                    description="A few details for the host."
                    onSaved={() => setChanging(false)}
                />
            )}
            <DeclineDialog
                open={declining}
                onOpenChange={setDeclining}
                url={preview ? null : (actions?.respond ?? null)}
                onDeclined={() => setChanging(false)}
            />
        </div>
    );
}
