import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

import { cn } from '@/lib/utils';

type AttentionRowProps = {
    icon: LucideIcon;
    tone: 'warning' | 'danger';
    title: string;
    description?: string;
    action: string;
    href: string;
};

const tones = {
    warning: 'bg-warning-muted text-warning',
    danger: 'bg-destructive-muted text-destructive',
};

/** A "Needs attention" line: tinted icon, what's wrong, and where to fix it. */
export function AttentionRow({
    icon: Icon,
    tone,
    title,
    description,
    action,
    href,
}: AttentionRowProps) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 border-b border-border p-3 last:border-b-0 hover:bg-raised/60"
        >
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-md',
                    tones[tone],
                )}
            >
                <Icon className="size-4" strokeWidth={1.75} />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold">{title}</span>
                {description && (
                    <span className="block truncate text-xs text-subtle">
                        {description}
                    </span>
                )}
            </span>
            <span className="text-xs font-bold text-link">{action}</span>
        </Link>
    );
}
