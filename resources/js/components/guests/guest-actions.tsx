import { router } from '@inertiajs/react';
import {
    BellRing,
    Check,
    Clock,
    Copy,
    MoreHorizontal,
    Pencil,
    RotateCw,
    Send,
    Trash2,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';

import { rsvpDisplayStatus } from '@/components/rsvps/rsvp-status';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { approve, destroy, reject, waitlist } from '@/routes/client/guests';
import { store as createRsvp } from '@/routes/client/guests/rsvps';
import { remind, resend, send } from '@/routes/client/rsvps';
import { messageAllowance } from '@/lib/guests';
import type { Guest, MessageLimits, RegistrationType } from '@/types';

type GuestActionsProps = {
    guest: Guest;
    /** Decides the approval actions: all of them for approval_required, none for guest_list_only. */
    registrationType: RegistrationType;
    /** The event's per-guest message limits; used-up actions are disabled. */
    messageLimits?: MessageLimits;
    onEdit: () => void;
};

/**
 * Per-row actions: the one next step for the guest as a button (approve,
 * invite, send or remind), everything else in a ⋯ menu.
 */
export function GuestActions({
    guest,
    registrationType,
    messageLimits,
    onEdit,
}: GuestActionsProps) {
    const requiresApproval = registrationType === 'approval_required';
    // Guest-list-only events have no approval; everyone on the list is approved.
    const allowsApproval = registrationType !== 'guest_list_only';

    const post = (url: string, data: Record<string, boolean> = {}) =>
        router.post(url, data, { preserveScroll: true });

    const rsvp = guest.latest_rsvp;
    const status = rsvp ? rsvpDisplayStatus(rsvp) : null;
    const isApproved = guest.approval_status === 'approved';
    const canInvite =
        isApproved &&
        (!status ||
            ['accepted', 'declined', 'maybe', 'expired'].includes(status));
    const noPhone = !guest.phone;
    const noPhoneHint = 'Add a phone number to send on WhatsApp';
    const allowance = messageAllowance(guest, messageLimits);

    // Pending requests are approved from the button; waitlisted / rejected
    // guests (and leftovers after the type changed) from the menu.
    const approveIsPrimary =
        allowsApproval &&
        !isApproved &&
        (guest.approval_status === 'pending' || !requiresApproval);

    let primary: ReactNode = null;

    if (approveIsPrimary) {
        primary = (
            <Button
                size="sm"
                variant="outline"
                onClick={() => post(approve.url(guest))}
            >
                <Check className="text-emerald-600" /> Approve
            </Button>
        );
    } else if (canInvite) {
        primary = (
            <Button
                size="sm"
                variant="outline"
                title={
                    noPhone
                        ? noPhoneHint
                        : (allowance.invitationsHint ??
                          'Create and send an RSVP link on WhatsApp')
                }
                disabled={noPhone || !allowance.invitations}
                onClick={() => post(createRsvp.url(guest), { send: true })}
            >
                <Send /> Invite
            </Button>
        );
    } else if (rsvp && status === 'pending') {
        primary = (
            <Button
                size="sm"
                variant="outline"
                title={
                    noPhone
                        ? noPhoneHint
                        : (allowance.invitationsHint ??
                          'Send the RSVP link on WhatsApp')
                }
                disabled={noPhone || !allowance.invitations}
                onClick={() => post(send.url(rsvp))}
            >
                <Send /> Send
            </Button>
        );
    } else if (rsvp && status === 'sent') {
        primary = (
            <Button
                size="sm"
                variant="outline"
                title={
                    noPhone
                        ? noPhoneHint
                        : (allowance.remindersHint ??
                          'Remind the guest to reply')
                }
                disabled={noPhone || !allowance.reminders}
                onClick={() => post(remind.url(rsvp))}
            >
                <BellRing /> Remind
            </Button>
        );
    }

    const linkActive = status === 'pending' || status === 'sent';
    const approvalItems = [
        allowsApproval && !isApproved && !approveIsPrimary && (
            <DropdownMenuItem
                key="approve"
                onClick={() => post(approve.url(guest))}
            >
                <Check className="text-emerald-600" /> Approve
            </DropdownMenuItem>
        ),
        requiresApproval && guest.approval_status !== 'waitlisted' && (
            <DropdownMenuItem
                key="waitlist"
                onClick={() => post(waitlist.url(guest))}
            >
                <Clock className="text-violet-600" /> Waitlist
            </DropdownMenuItem>
        ),
        requiresApproval && guest.approval_status !== 'rejected' && (
            <DropdownMenuItem
                key="reject"
                onClick={() => post(reject.url(guest))}
            >
                <X className="text-red-600" /> Reject
            </DropdownMenuItem>
        ),
    ].filter(Boolean);

    return (
        <div className="flex items-center justify-end gap-1">
            {primary}
            <DropdownMenu>
                <DropdownMenuTrigger
                    aria-label={`More actions for ${guest.name}`}
                    className="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none data-popup-open:bg-muted"
                >
                    <MoreHorizontal className="size-4" />
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    {rsvp && status === 'sent' && (
                        <DropdownMenuItem
                            disabled={noPhone || !allowance.invitations}
                            onClick={() => post(resend.url(rsvp))}
                        >
                            <RotateCw /> Resend invitation
                        </DropdownMenuItem>
                    )}
                    {rsvp && linkActive && (
                        <DropdownMenuItem
                            onClick={() =>
                                navigator.clipboard.writeText(
                                    guest.link_url ?? rsvp.rsvp_url,
                                )
                            }
                        >
                            <Copy /> Copy RSVP link
                        </DropdownMenuItem>
                    )}
                    {rsvp && linkActive && <DropdownMenuSeparator />}

                    {approvalItems}
                    {approvalItems.length > 0 && <DropdownMenuSeparator />}

                    <DropdownMenuItem onClick={onEdit}>
                        <Pencil /> Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        variant="destructive"
                        onClick={() =>
                            confirm(
                                `Remove ${guest.name} from the guest list?`,
                            ) &&
                            router.delete(destroy.url(guest), {
                                preserveScroll: true,
                            })
                        }
                    >
                        <Trash2 /> Remove
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
