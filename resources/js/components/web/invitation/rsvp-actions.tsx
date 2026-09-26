import { router } from '@inertiajs/react';
import { useState } from 'react';

import type { InvitationPageProps, Rsvp } from '@/types/web';

type RsvpActionsProps = Pick<InvitationPageProps, 'mode' | 'rsvp' | 'actions'>;

type State = 'awaiting' | 'accepted' | 'declined' | 'expired' | 'preview';

function stateOf(mode: string, rsvp: Rsvp | null): State {
    if (mode === 'preview' || !rsvp) {
        return 'preview';
    }

    if (rsvp.status === 'accepted' || rsvp.status === 'declined') {
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
    expired: 'This invitation is no longer accepting responses.',
    preview: 'Will you be joining us?',
};

/**
 * Accept / decline. Rendered inside the invitation's shadow root, so it
 * uses plain class names (.emp-rsvp…) that templates can style.
 */
export function RsvpActions({ mode, rsvp, actions }: RsvpActionsProps) {
    const [processing, setProcessing] = useState(false);
    const state = stateOf(mode, rsvp);

    const respond = (url: string) =>
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );

    const showButtons = state === 'awaiting' || state === 'preview';

    return (
        <div className="emp-rsvp" data-state={state}>
            <p className="emp-rsvp__message" role="status">
                {messages[state]}
            </p>
            {showButtons && (
                <div className="emp-rsvp__actions">
                    <button
                        type="button"
                        className="emp-rsvp__button emp-rsvp__button--accept"
                        disabled={state === 'preview' || processing}
                        onClick={() => actions && respond(actions.accept)}
                    >
                        Accept
                    </button>
                    <button
                        type="button"
                        className="emp-rsvp__button emp-rsvp__button--decline"
                        disabled={state === 'preview' || processing}
                        onClick={() => actions && respond(actions.decline)}
                    >
                        Decline
                    </button>
                </div>
            )}
            {state === 'preview' && (
                <p className="emp-rsvp__note">
                    Preview: guests will be able to respond here.
                </p>
            )}
        </div>
    );
}
