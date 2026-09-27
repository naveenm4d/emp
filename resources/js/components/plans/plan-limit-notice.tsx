import { Link } from '@inertiajs/react';
import { ChevronRight, Lock } from 'lucide-react';

import { useClientPlan } from '@/lib/plans';
import { cn } from '@/lib/utils';
import { membership } from '@/routes/client';

/**
 * Why no event can be created now, with a link to the Membership page.
 * Renders nothing while the client's plan still allows new events.
 */
export function PlanLimitNotice({ className }: { className?: string }) {
    const plan = useClientPlan();

    if (!plan || plan.can_create_event) {
        return null;
    }

    return (
        <Link
            href={membership.url()}
            className={cn(
                'mb-4 flex items-center gap-3 rounded-xl border border-warning/30 bg-warning-muted px-3.5 py-3 text-sm',
                className,
            )}
        >
            <Lock className="size-4 shrink-0 text-warning" strokeWidth={2} />
            <span className="min-w-0 flex-1">
                {plan.reason ?? 'Your plan doesn’t allow new events right now.'}
            </span>
            <span className="inline-flex shrink-0 items-center gap-0.5 text-xs font-semibold text-link">
                View membership <ChevronRight className="size-3.5" />
            </span>
        </Link>
    );
}
