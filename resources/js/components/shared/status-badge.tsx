import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    Check,
    CircleDashed,
    Globe,
    Pencil,
    X,
} from 'lucide-react';

import { cn } from '@/lib/utils';

const tones: Record<string, string> = {
    green: 'bg-success-muted text-success',
    blue: 'bg-info-muted text-info',
    amber: 'bg-warning-muted text-warning',
    violet: 'bg-accent text-accent-foreground',
    gray: 'bg-foreground/[0.06] text-muted-foreground',
    red: 'bg-destructive-muted text-destructive',
};

const statusTone: Record<string, keyof typeof tones> = {
    published: 'green',
    approved: 'green',
    accepted: 'green',
    confirmed: 'green',
    delivered: 'green',
    read: 'violet',
    active: 'green',
    sent: 'blue',
    super_admin: 'violet',
    admin: 'blue',
    draft: 'gray',
    pending: 'amber',
    maybe: 'amber',
    waitlisted: 'violet',
    not_sent: 'gray',
    expired: 'gray',
    inactive: 'gray',
    viewer: 'gray',
    cancelled: 'red',
    rejected: 'red',
    declined: 'red',
    failed: 'red',
};

/** Leading icons for the statuses the design draws with one. */
const statusIcon: Record<string, LucideIcon> = {
    published: Globe,
    draft: Pencil,
    confirmed: Check,
    accepted: Check,
    approved: Check,
    declined: X,
    rejected: X,
    cancelled: X,
    failed: AlertTriangle,
    sent: CircleDashed,
    not_sent: CircleDashed,
};

type StatusBadgeProps = {
    status: string;
    label?: string;
    className?: string;
};

export function StatusBadge({ status, label, className }: StatusBadgeProps) {
    const Icon = statusIcon[status];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-[3px] text-[11px] font-semibold whitespace-nowrap',
                tones[statusTone[status] ?? 'gray'],
                className,
            )}
        >
            {Icon ? (
                <Icon className="size-3" strokeWidth={2} />
            ) : (
                <span className="size-1.5 rounded-full bg-current opacity-70" />
            )}
            {label ??
                status
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, (c) => c.toUpperCase())}
        </span>
    );
}
