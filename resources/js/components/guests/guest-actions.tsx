import { router } from '@inertiajs/react';
import { Check, Clock, Pencil, Send, Trash2, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { approve, destroy, reject, waitlist } from '@/routes/client/guests';
import { store as createInvitation } from '@/routes/client/guests/invitations';
import { resend, send } from '@/routes/client/invitations';
import type { Guest } from '@/types';

type GuestActionsProps = { guest: Guest; onEdit: () => void };

/** Per-row actions: approval, invitation delivery, edit and delete. */
export function GuestActions({ guest, onEdit }: GuestActionsProps) {
    const post = (url: string, data: Record<string, boolean> = {}) =>
        router.post(url, data, { preserveScroll: true });
    const invitation = guest.latest_invitation;
    const canInvite = guest.approval_status === 'approved';

    return (
        <div className="flex items-center justify-end gap-1">
            {guest.approval_status !== 'approved' && (
                <Button
                    size="icon-sm"
                    variant="ghost"
                    title="Approve"
                    onClick={() => post(approve.url(guest))}
                >
                    <Check className="text-emerald-600" />
                </Button>
            )}
            {guest.approval_status !== 'waitlisted' && (
                <Button
                    size="icon-sm"
                    variant="ghost"
                    title="Waitlist"
                    onClick={() => post(waitlist.url(guest))}
                >
                    <Clock className="text-violet-600" />
                </Button>
            )}
            {guest.approval_status !== 'rejected' && (
                <Button
                    size="icon-sm"
                    variant="ghost"
                    title="Reject"
                    onClick={() => post(reject.url(guest))}
                >
                    <X className="text-red-600" />
                </Button>
            )}

            {canInvite &&
                (!invitation ||
                    ['accepted', 'declined', 'expired'].includes(
                        invitation.status,
                    )) && (
                    <Button
                        size="sm"
                        variant="outline"
                        title="Create and send a WhatsApp invitation"
                        onClick={() =>
                            post(createInvitation.url(guest), { send: true })
                        }
                        disabled={!guest.phone}
                    >
                        <Send /> Invite
                    </Button>
                )}
            {invitation?.status === 'pending' && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => post(send.url(invitation))}
                    disabled={!guest.phone}
                >
                    <Send /> Send
                </Button>
            )}
            {invitation?.status === 'sent' && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => post(resend.url(invitation))}
                >
                    <Send /> Resend
                </Button>
            )}

            <Button
                size="icon-sm"
                variant="ghost"
                title="Edit"
                onClick={onEdit}
            >
                <Pencil />
            </Button>
            <Button
                size="icon-sm"
                variant="ghost"
                title="Remove"
                onClick={() =>
                    confirm(`Remove ${guest.name} from the guest list?`) &&
                    router.delete(destroy.url(guest), { preserveScroll: true })
                }
            >
                <Trash2 />
            </Button>
        </div>
    );
}
