import type { ReactNode } from 'react';

import { StatusBadge } from '@/components/shared/status-badge';
import { formatDateTime, timeAgo } from '@/lib/format';

type StatusWithTimeProps = {
    status: string;
    /** When the status was reached; shown as "3 days ago", the exact time on hover. */
    at?: string | null;
    /** Verb before the time, e.g. "sent" → "sent 2 hours ago". */
    verb?: string;
    /** Extra muted lines under the time (e.g. expiry, reminders). */
    children?: ReactNode;
    title?: string;
};

/** A status badge with a muted "how long ago" line under it. */
export function StatusWithTime({
    status,
    at,
    verb,
    children,
    title,
}: StatusWithTimeProps) {
    return (
        <div className="flex flex-col items-start gap-0.5" title={title}>
            <StatusBadge status={status} />
            {at && (
                <span
                    className="text-[11px] leading-tight text-muted-foreground"
                    title={formatDateTime(at)}
                >
                    {verb ? `${verb} ${timeAgo(at)}` : timeAgo(at)}
                </span>
            )}
            {children}
        </div>
    );
}
