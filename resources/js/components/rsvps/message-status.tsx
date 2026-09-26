import { StatusWithTime } from '@/components/shared/status-with-time';
import type { RsvpMessage } from '@/types';

/** WhatsApp delivery status of an RSVP link's latest message (updated by provider webhooks). */
export function MessageStatus({ message }: { message?: RsvpMessage | null }) {
    if (!message) {
        return <span className="text-xs text-muted-foreground">—</span>;
    }

    const at = {
        pending: null,
        sent: message.sent_at,
        delivered: message.delivered_at,
        read: message.read_at,
        failed: message.updated_at,
    }[message.status];

    return (
        <StatusWithTime
            status={message.status}
            at={at}
            title={message.error ?? undefined}
        />
    );
}
