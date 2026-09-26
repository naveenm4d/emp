import { cn } from '@/lib/utils';

const tones: Record<string, string> = {
    green: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20',
    blue: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:border-sky-500/20',
    amber: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20',
    violet: 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:border-violet-500/20',
    gray: 'bg-gray-50 text-gray-600 border-gray-200 dark:bg-white/5 dark:text-gray-300 dark:border-white/10',
    red: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/20',
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
    draft: 'amber',
    pending: 'amber',
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

type StatusBadgeProps = {
    status: string;
    label?: string;
    className?: string;
};

export function StatusBadge({ status, label, className }: StatusBadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                tones[statusTone[status] ?? 'gray'],
                className,
            )}
        >
            <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
            {label ??
                status
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, (c) => c.toUpperCase())}
        </span>
    );
}
