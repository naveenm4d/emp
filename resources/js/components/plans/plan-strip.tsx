import { Sparkles } from 'lucide-react';

import { planSummary, useClientPlan } from '@/lib/plans';
import { cn } from '@/lib/utils';

/**
 * The client's plan at a glance ("Starter · 1 of 1 events used"), and why
 * no event can be created now, with a link to the plans.
 */
export function PlanStrip({ className }: { className?: string }) {
    const plan = useClientPlan();

    if (!plan) {
        return null;
    }

    const blocked = !plan.can_create_event;

    return (
        <div
            className={cn(
                'mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm',
                blocked
                    ? 'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40'
                    : 'border-border bg-muted/40',
                className,
            )}
        >
            <p>
                <span className="font-medium">{planSummary(plan)}</span>
                {blocked && plan.reason && (
                    <span className="block text-xs text-muted-foreground sm:inline sm:before:content-['_·_']">
                        {plan.reason}
                    </span>
                )}
            </p>
            {(blocked || plan.plan === 'starter') && (
                <a
                    href="/#pricing"
                    className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                >
                    <Sparkles className="size-3.5" /> See plans
                </a>
            )}
        </div>
    );
}
