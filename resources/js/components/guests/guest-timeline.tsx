import { useHttp } from '@inertiajs/react';
import {
    BellRing,
    CircleCheck,
    CircleX,
    Clock,
    Eye,
    Link2,
    MessageSquare,
    Send,
    ShieldCheck,
    UserPlus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { StatusBadge } from '@/components/shared/status-badge';
import { formatDateTime, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { details } from '@/routes/client/guests';
import type { Guest, Notification, Rsvp } from '@/types';

type TimelineData = { rsvps: Rsvp[]; messages: Notification[] };

type Entry = {
    key: string;
    at: string;
    icon: LucideIcon;
    tone: 'default' | 'success' | 'danger' | 'muted';
    title: string;
    body?: ReactNode;
};

const toneClass: Record<Entry['tone'], string> = {
    default: 'bg-primary/10 text-primary',
    success: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    danger: 'bg-red-500/10 text-red-600 dark:text-red-400',
    muted: 'bg-muted text-muted-foreground',
};

const approvalTitles: Record<Guest['approval_status'], string> = {
    approved: 'Approved',
    pending: 'Waiting for approval',
    waitlisted: 'Waitlisted',
    rejected: 'Rejected',
};

/**
 * Everything that happened with a guest, newest first: added, approval, RSVP
 * links, replies, link opens and every WhatsApp message with its delivery steps.
 */
export function GuestTimeline({
    guest,
    refreshKey,
}: {
    guest: Guest;
    /** Changes when the guest's statuses change, so the history reloads. */
    refreshKey: string;
}) {
    const http = useHttp<Record<string, never>, TimelineData>();
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        setFailed(false);
        http.get(details.url(guest)).catch(() => setFailed(true));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [guest.id, refreshKey]);

    if (failed) {
        return (
            <p className="text-sm text-muted-foreground">
                Couldn't load the history. Close and open the guest again.
            </p>
        );
    }

    if (!http.response) {
        return (
            <div className="space-y-4">
                {[0, 1, 2].map((i) => (
                    <div key={i} className="flex gap-3">
                        <div className="size-7 shrink-0 animate-pulse rounded-full bg-muted" />
                        <div className="flex-1 space-y-2 pt-1">
                            <div className="h-3 w-2/3 animate-pulse rounded bg-muted" />
                            <div className="h-3 w-1/3 animate-pulse rounded bg-muted" />
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    const entries = buildEntries(guest, http.response);

    return (
        <ol className="relative space-y-5 before:absolute before:top-2 before:bottom-2 before:left-3.5 before:w-px before:bg-border">
            {entries.map((entry) => (
                <li key={entry.key} className="relative flex gap-3">
                    <span
                        className={cn(
                            'relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full ring-4 ring-background',
                            toneClass[entry.tone],
                        )}
                    >
                        <entry.icon className="size-3.5" />
                    </span>
                    <div className="min-w-0 flex-1 pt-0.5">
                        <div className="flex flex-wrap items-baseline justify-between gap-x-2">
                            <p className="font-medium">{entry.title}</p>
                            <time
                                dateTime={entry.at}
                                title={formatDateTime(entry.at)}
                                className="text-xs text-muted-foreground"
                            >
                                {timeAgo(entry.at)}
                            </time>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {formatDateTime(entry.at)}
                        </p>
                        {entry.body && <div className="mt-2">{entry.body}</div>}
                    </div>
                </li>
            ))}
        </ol>
    );
}

function buildEntries(guest: Guest, { rsvps, messages }: TimelineData) {
    const entries: Entry[] = [
        {
            key: 'added',
            at: guest.created_at,
            icon: UserPlus,
            tone: 'muted',
            title:
                guest.source === 'public_link'
                    ? 'Registered through the public link'
                    : 'Added to the guest list',
        },
    ];

    if (
        guest.approval_status_changed_at &&
        Math.abs(
            new Date(guest.approval_status_changed_at).getTime() -
                new Date(guest.created_at).getTime(),
        ) > 1000
    ) {
        entries.push({
            key: 'approval',
            at: guest.approval_status_changed_at,
            icon: ShieldCheck,
            tone:
                guest.approval_status === 'rejected'
                    ? 'danger'
                    : guest.approval_status === 'approved'
                      ? 'success'
                      : 'muted',
            title: approvalTitles[guest.approval_status],
        });
    }

    for (const rsvp of rsvps) {
        entries.push({
            key: `rsvp-${rsvp.id}`,
            at: rsvp.created_at,
            icon: Link2,
            tone: 'muted',
            title: 'RSVP link created',
        });

        if (rsvp.responded_at && rsvp.status !== 'expired') {
            entries.push({
                key: `reply-${rsvp.id}`,
                at: rsvp.responded_at,
                icon: rsvp.status === 'declined' ? CircleX : CircleCheck,
                tone: rsvp.status === 'declined' ? 'danger' : 'success',
                title:
                    rsvp.status === 'declined'
                        ? 'Declined the invitation'
                        : 'Accepted the invitation',
            });
        }

        if (rsvp.is_expired && rsvp.expires_at && !rsvp.responded_at) {
            entries.push({
                key: `expired-${rsvp.id}`,
                at: rsvp.expires_at,
                icon: Clock,
                tone: 'muted',
                title: 'RSVP link expired',
            });
        }
    }

    // Oldest first, to tell a first invitation from a re-send per RSVP link.
    const invitedLinks = new Set<string>();

    for (const message of [...messages].reverse()) {
        const isReminder = message.kind === 'rsvp_reminder';
        const isInvitation = message.kind === 'rsvp_invitation';
        const resent =
            isInvitation &&
            !!message.rsvp_id &&
            invitedLinks.has(message.rsvp_id);

        if (isInvitation && message.rsvp_id) {
            invitedLinks.add(message.rsvp_id);
        }

        entries.push({
            key: `message-${message.id}`,
            at: message.created_at,
            icon: isReminder ? BellRing : isInvitation ? Send : MessageSquare,
            tone: message.status === 'failed' ? 'danger' : 'default',
            title: isReminder
                ? 'Reminder sent'
                : resent
                  ? 'Invitation re-sent'
                  : isInvitation
                    ? 'Invitation sent'
                    : 'Message sent',
            body: <MessageDetails message={message} />,
        });
    }

    if (guest.link_last_opened_at && (guest.link_open_count ?? 0) > 0) {
        entries.push({
            key: 'opened',
            at: guest.link_last_opened_at,
            icon: Eye,
            tone: 'muted',
            title:
                guest.link_open_count === 1
                    ? 'Opened their link'
                    : `Opened their link (${guest.link_open_count}× in total)`,
        });
    }

    return entries.sort(
        (a, b) => new Date(b.at).getTime() - new Date(a.at).getTime(),
    );
}

/** The message text (collapsible) and its delivery steps. */
function MessageDetails({ message }: { message: Notification }) {
    const [expanded, setExpanded] = useState(false);

    const steps = [
        { status: 'sent', at: message.sent_at },
        { status: 'delivered', at: message.delivered_at },
        { status: 'read', at: message.read_at },
    ].filter((step) => step.at);

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap gap-1.5">
                {message.status === 'pending' && (
                    <StatusBadge status="pending" label="Queued" />
                )}
                {steps.map((step) => (
                    <span
                        key={step.status}
                        title={formatDateTime(step.at)}
                        className="inline-flex items-center gap-1"
                    >
                        <StatusBadge status={step.status} />
                        <span className="text-[11px] text-muted-foreground">
                            {timeAgo(step.at)}
                        </span>
                    </span>
                ))}
                {message.status === 'failed' && <StatusBadge status="failed" />}
            </div>
            {message.status === 'failed' && message.error && (
                <p className="text-xs text-destructive">{message.error}</p>
            )}
            <button
                type="button"
                onClick={() => setExpanded((shown) => !shown)}
                className={cn(
                    'block w-full rounded-lg bg-muted/50 p-2.5 text-left text-xs break-words whitespace-pre-line',
                    !expanded && 'line-clamp-2',
                )}
                aria-expanded={expanded}
                title={expanded ? 'Show less' : 'Show the full message'}
            >
                {message.message}
            </button>
        </div>
    );
}
