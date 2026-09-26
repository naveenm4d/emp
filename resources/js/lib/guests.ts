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
