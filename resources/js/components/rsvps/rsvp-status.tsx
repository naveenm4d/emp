import { BellRing, Eye } from 'lucide-react';

import { StatusWithTime } from '@/components/shared/status-with-time';
import { formatDateTime, timeAgo } from '@/lib/format';
import type { Rsvp } from '@/types';

/** A link still marked sent whose expiry has passed reads as expired. */
export function rsvpDisplayStatus(rsvp: Rsvp): Rsvp['status'] {
    return rsvp.is_expired && rsvp.status === 'sent' ? 'expired' : rsvp.status;
}

/**
 * An RSVP link's status with when it got there ("sent 23 hours ago"), when it
 * expires while awaiting a reply, and how often the guest was reminded.
 */
export function RsvpStatus({
    rsvp,
    opened,
}: {
    rsvp: Rsvp;
    /** How often the guest opened their link (people only, not link previews). */
    opened?: { count: number; at: string | null };
}) {
    const status = rsvpDisplayStatus(rsvp);

    const [verb, at] = {
        pending: ['created', rsvp.created_at],
        sent: ['sent', rsvp.sent_at],
        accepted: ['replied', rsvp.responded_at],
        declined: ['replied', rsvp.responded_at],
        expired: ['expired', rsvp.expires_at],
    }[status] as [string, string | null];

    return (
        <StatusWithTime status={status} verb={verb} at={at}>
            {status === 'sent' && rsvp.expires_at && (
                <span
                    className="text-[11px] leading-tight text-muted-foreground"
                    title={formatDateTime(rsvp.expires_at)}
                >
                    expires {timeAgo(rsvp.expires_at)}
                </span>
            )}
            {rsvp.reminder_count > 0 && (
                <span
                    className="inline-flex items-center gap-1 text-[11px] leading-tight text-muted-foreground"
                    title={formatDateTime(rsvp.last_reminded_at)}
                >
                    <BellRing className="size-3" />
                    Reminded {rsvp.reminder_count}× · last{' '}
                    {timeAgo(rsvp.last_reminded_at)}
                </span>
            )}
            {opened && opened.count > 0 && (
                <span
                    className="inline-flex items-center gap-1 text-[11px] leading-tight text-muted-foreground"
                    title={formatDateTime(opened.at)}
                >
                    <Eye className="size-3" />
                    opened {timeAgo(opened.at)} · {opened.count}×
                </span>
            )}
        </StatusWithTime>
    );
}
