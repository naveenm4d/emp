import { router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BellRing,
    Check,
    CircleCheck,
    Clock,
    PanelRightOpen,
    Pencil,
    RotateCw,
    Share2,
    Send,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import { rsvpDisplayStatus } from '@/components/rsvps/rsvp-status';
import { Button } from '@/components/ui/button';
import { ConfirmBar, useConfirm } from '@/components/shared/confirm-bar';
import { approve, destroy, reject, waitlist } from '@/routes/client/guests';
import { store as createRsvp } from '@/routes/client/guests/rsvps';
import { remind, resend, send } from '@/routes/client/rsvps';
import { messageAllowance } from '@/lib/guests';
import { useClientCan } from '@/lib/permissions';
import { cn } from '@/lib/utils';
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
 * A guest row's actions: its one next step as a button (invite them, send
 * the link that isn't out yet, or remind them) and an icon that opens the
 * details panel, where everything else is.
 */
export function GuestActions({
    guest,
    messageLimits,
    onView,
}: Pick<GuestActionsProps, 'guest' | 'messageLimits'> & {
    /** Opens the guest details panel. */
    onView: () => void;
}) {
    const canSend = useClientCan()('messages.send');
    const rsvp = guest.latest_rsvp;
    const status = rsvp ? rsvpDisplayStatus(rsvp) : null;
    const canInvite =
        guest.approval_status === 'approved' &&
        (!status ||
            ['accepted', 'declined', 'maybe', 'expired'].includes(status));
    const noPhone = !guest.phone;
    const noPhoneHint = 'Add a phone number to send on WhatsApp';
    const allowance = messageAllowance(guest, messageLimits);
    const post = (url: string, data: Record<string, boolean> = {}) =>
        router.post(url, data, { preserveScroll: true });

    const action = !canSend
        ? null
        : canInvite
          ? {
                label: status ? 'Re-invite' : 'Invite',
                icon: Send,
                disabled: noPhone || !allowance.invitations,
                hint: noPhone
                    ? noPhoneHint
                    : (allowance.invitationsHint ??
                      'Send an RSVP link on WhatsApp'),
                run: () => post(createRsvp.url(guest), { send: true }),
            }
          : rsvp && status === 'pending'
            ? {
                  label: 'Invite',
                  icon: Send,
                  disabled: noPhone || !allowance.invitations,
                  hint: noPhone
                      ? noPhoneHint
                      : (allowance.invitationsHint ??
                        'Send the RSVP link on WhatsApp'),
                  run: () => post(send.url(rsvp)),
              }
            : rsvp && status === 'sent'
              ? {
                    label: 'Remind',
                    icon: BellRing,
                    disabled: noPhone || !allowance.reminders,
                    hint: noPhone
                        ? noPhoneHint
                        : (allowance.remindersHint ??
                          'Remind the guest to reply'),
                    run: () => post(remind.url(rsvp)),
                }
              : null;

    return (
        <div className="flex items-center justify-end gap-1">
            {action && (
                <Button
                    size="sm"
                    variant="outline"
                    title={action.hint}
                    disabled={action.disabled}
                    onClick={(e) => {
                        e.stopPropagation();
                        action.run();
                    }}
                >
                    <action.icon /> {action.label}
                </Button>
            )}
            <button
                type="button"
                aria-label={`View details: ${guest.name}`}
                title="View details"
                onClick={(e) => {
                    e.stopPropagation();
                    onView();
                }}
                className="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors outline-none hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50"
            >
                <PanelRightOpen className="size-4" strokeWidth={1.9} />
            </button>
        </div>
    );
}

type QuickAction = {
    key: string;
    label: string;
    icon: LucideIcon;
    onClick?: () => void;
    href?: string;
    disabled?: boolean;
    title?: string;
    /** The guest's next step, drawn filled. */
    primary?: boolean;
    danger?: boolean;
};

/**
 * Every action for one guest as round icon buttons with a label (the guest
 * details panel): the next step first and filled, then the link, contact,
 * approval, edit and remove. Same rules as GuestActions.
 */
export function GuestQuickActions({
    guest,
    registrationType,
    messageLimits,
    onEdit,
}: GuestActionsProps) {
    const [copied, setCopied] = useState(false);
    const confirmation = useConfirm();
    const can = useClientCan();
    const canManage = can('guests.manage');
    const canSend = can('messages.send');

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timer = setTimeout(() => setCopied(false), 1500);

        return () => clearTimeout(timer);
    }, [copied]);

    const requiresApproval = registrationType === 'approval_required';
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
    const link = guest.link_url ?? rsvp?.rsvp_url ?? null;

    const actions: (QuickAction | false)[] = [
        canManage &&
            allowsApproval &&
            !isApproved && {
                key: 'approve',
                label: 'Approve',
                icon: Check,
                primary: true,
                onClick: () => post(approve.url(guest)),
            },
        canSend &&
            canInvite && {
                key: 'invite',
                label: status ? 'Re-invite' : 'Invite',
                icon: Send,
                primary: true,
                disabled: noPhone || !allowance.invitations,
                title: noPhone
                    ? noPhoneHint
                    : (allowance.invitationsHint ??
                      'Create and send an RSVP link on WhatsApp'),
                onClick: () => post(createRsvp.url(guest), { send: true }),
            },
        canSend &&
            !!rsvp &&
            status === 'pending' && {
                key: 'send',
                label: 'Send',
                icon: Send,
                primary: true,
                disabled: noPhone || !allowance.invitations,
                title: noPhone
                    ? noPhoneHint
                    : (allowance.invitationsHint ??
                      'Send the RSVP link on WhatsApp'),
                onClick: () => post(send.url(rsvp)),
            },
        canSend &&
            !!rsvp &&
            status === 'sent' && {
                key: 'remind',
                label: 'Remind',
                icon: BellRing,
                primary: true,
                disabled: noPhone || !allowance.reminders,
                title: noPhone
                    ? noPhoneHint
                    : (allowance.remindersHint ?? 'Remind the guest to reply'),
                onClick: () => post(remind.url(rsvp)),
            },
        canSend &&
            !!rsvp &&
            status === 'sent' && {
                key: 'resend',
                label: 'Resend',
                icon: RotateCw,
                disabled: noPhone || !allowance.invitations,
                title: allowance.invitationsHint ?? 'Send the invitation again',
                onClick: () => post(resend.url(rsvp)),
            },
        !!link && {
            key: 'share',
            label: copied ? 'Copied' : 'Share link',
            icon: copied ? CircleCheck : Share2,
            title: 'Share their personal RSVP link',
            onClick: () => shareLink(guest.name, link, () => setCopied(true)),
        },
        canManage &&
            requiresApproval &&
            guest.approval_status !== 'waitlisted' && {
                key: 'waitlist',
                label: 'Waitlist',
                icon: Clock,
                onClick: () => post(waitlist.url(guest)),
            },
        canManage &&
            requiresApproval &&
            guest.approval_status !== 'rejected' && {
                key: 'reject',
                label: 'Reject',
                icon: X,
                danger: true,
                onClick: () =>
                    confirmation.ask({
                        title: `Reject ${guest.name}? They won’t be invited.`,
                        confirmLabel: 'Reject',
                        onConfirm: () => post(reject.url(guest)),
                    }),
            },
        canManage && {
            key: 'edit',
            label: 'Edit',
            icon: Pencil,
            onClick: onEdit,
        },
        canManage && {
            key: 'remove',
            label: 'Remove',
            icon: Trash2,
            danger: true,
            onClick: () =>
                confirmation.ask({
                    title: `Remove ${guest.name} from the guest list? Their RSVP link stops working.`,
                    confirmLabel: 'Remove',
                    onConfirm: () =>
                        router.delete(destroy.url(guest), {
                            preserveScroll: true,
                        }),
                }),
        },
    ];

    // Only one filled "next step".
    let hasPrimary = false;
    const shown = actions.filter(Boolean).map((action) => {
        const item = action as QuickAction;
        const primary = !!item.primary && !hasPrimary;
        hasPrimary ||= primary;

        return { ...item, primary };
    });

    return (
        <div className="relative grid grid-cols-4 gap-x-2 gap-y-3.5 rounded-2xl">
            <ConfirmBar
                request={confirmation.request}
                onCancel={confirmation.cancel}
            />
            {shown.map((action) => {
                const Icon = action.icon;
                const circle = cn(
                    'flex size-12 items-center justify-center rounded-full transition-colors',
                    action.primary
                        ? 'bg-strong text-strong-foreground'
                        : action.danger
                          ? 'bg-destructive-muted text-destructive'
                          : 'bg-card text-foreground shadow-card group-hover:bg-raised',
                );
                const content = (
                    <>
                        <span className={circle}>
                            <Icon className="size-5" strokeWidth={1.75} />
                        </span>
                        <span className="max-w-full truncate text-[11px] font-semibold text-muted-foreground">
                            {action.label}
                        </span>
                    </>
                );
                const className =
                    'group flex min-w-0 flex-col items-center gap-1.5 outline-none focus-visible:[&>span:first-child]:ring-3 focus-visible:[&>span:first-child]:ring-ring/50 disabled:opacity-40';

                return action.href ? (
                    <a
                        key={action.key}
                        href={action.href}
                        target={
                            action.href.startsWith('http')
                                ? '_blank'
                                : undefined
                        }
                        rel="noreferrer"
                        title={action.title ?? action.label}
                        className={className}
                    >
                        {content}
                    </a>
                ) : (
                    <button
                        key={action.key}
                        type="button"
                        title={action.title ?? action.label}
                        disabled={action.disabled}
                        onClick={action.onClick}
                        className={className}
                    >
                        {content}
                    </button>
                );
            })}
        </div>
    );
}

/** Opens the device's share sheet with the guest's link, or copies it where there is none. */
export function shareLink(name: string, url: string, onCopied: () => void) {
    if (typeof navigator.share === 'function') {
        void navigator
            .share({ title: `RSVP link for ${name}`, url })
            .catch(() => {});

        return;
    }

    void navigator.clipboard.writeText(url).then(onCopied);
}
