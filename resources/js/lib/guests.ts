import { router } from '@inertiajs/react';

import type { Event, Guest, GuestSummary, MessageLimits } from '@/types';

/**
 * Whether guest approval is shown for an event: never for guest-list-only
 * events (every guest is added by the client); otherwise when registrations
 * need approval, or while some guests are still not approved (e.g. the type
 * was changed later), so those guests can still be approved and invited.
 */
export function showsApproval(
    event: Pick<Event, 'registration_type'>,
    summary: Pick<GuestSummary, 'total' | 'approved'>,
): boolean {
    if (event.registration_type === 'guest_list_only') {
        return false;
    }

    return (
        event.registration_type === 'approval_required' ||
        summary.total !== summary.approved
    );
}

/**
 * Whether the guest can still get an invitation / a reminder under the
 * event's per-guest limits (the server enforces them too), with the reason
 * to show on a disabled button.
 */
export function messageAllowance(
    guest: Pick<Guest, 'messages_sent'> | null | undefined,
    limits: MessageLimits | null | undefined,
) {
    const sent = guest?.messages_sent;
    const left = (used: number | undefined, limit: number | undefined) =>
        used === undefined || limit === undefined || used < limit;
    const reason = (limit: number, what: string) =>
        limit === 0
            ? `No ${what}s can be sent for this event`
            : `All ${limit} ${what}${limit === 1 ? '' : 's'} used for this guest`;

    const invitations = left(sent?.invitations, limits?.invitations);
    const reminders = left(sent?.reminders, limits?.reminders);

    return {
        invitations,
        reminders,
        invitationsHint:
            invitations || !limits
                ? null
                : reason(limits.invitations, 'invitation'),
        remindersHint:
            reminders || !limits ? null : reason(limits.reminders, 'reminder'),
    };
}

/**
 * Where a guest's invitation stands: "Invitation failed", "Invite opened",
 * "Delivered"… (the latest RSVP message, and whether the link was opened).
 */
export function invitationState(
    guest: Pick<Guest, 'latest_rsvp' | 'link_open_count'>,
): { label: string; failed: boolean } {
    const status = guest.latest_rsvp?.message?.status;

    if (status === 'failed') {
        return { label: 'Invitation failed', failed: true };
    }

    if ((guest.link_open_count ?? 0) > 0) {
        return { label: 'Invite opened', failed: false };
    }

    const labels: Record<string, string> = {
        pending: 'Queued',
        sent: 'Sent',
        delivered: 'Delivered',
        read: 'Read',
    };

    return { label: status ? labels[status] : 'Not sent', failed: false };
}

/** "1 of 2 reminders", "2 of 2 · limit reached". */
export function remindersUsed(
    guest: Pick<Guest, 'messages_sent'>,
    limits: MessageLimits,
): { label: string; atLimit: boolean } {
    const used = guest.messages_sent?.reminders ?? 0;
    const atLimit = used >= limits.reminders;

    return {
        label: atLimit
            ? `${used} of ${limits.reminders} · limit reached`
            : `${used} of ${limits.reminders} reminders`,
        atLimit,
    };
}

/** The guest open in the details panel, kept in the URL as `?guest={id}`. */
export function openGuestId(url: string): string | null {
    return new URL(url, window.location.origin).searchParams.get('guest');
}

/**
 * Opens (or, with null, closes) a guest's details panel: puts the guest in the
 * URL and loads just their history and answers into the `guestDetails` prop.
 */
export function loadGuestDetails(guestId: string | null): void {
    router.reload({
        data: { guest: guestId ?? undefined },
        only: ['guestDetails'],
        replace: true,
    });
}
