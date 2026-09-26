import type { Event, GuestSummary } from '@/types';

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
