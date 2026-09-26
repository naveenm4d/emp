import { usePage } from '@inertiajs/react';

import type { ClientPlanUsage, PlanFeature } from '@/types';

/** The signed-in client's plan and what's used / left (client dashboard only). */
export function useClientPlan(): ClientPlanUsage | null {
    return usePage().props.auth.client?.plan ?? null;
}

/** Whether the signed-in client's plan includes the feature (true when unknown, e.g. in admin). */
export function useHasPlanFeature(): (feature: PlanFeature) => boolean {
    const plan = useClientPlan();

    return (feature) => !plan || plan.features.includes(feature);
}

/** "Starter · 1 of 1 events used", "Celebration · 2 event credits left", "Business · active until 12 Oct 2027". */
export function planSummary(plan: ClientPlanUsage): string {
    if (plan.events_allowed !== null) {
        return `${plan.label} · ${plan.events_used} of ${plan.events_allowed} ${plan.events_allowed === 1 ? 'event' : 'events'} used`;
    }

    if (plan.plan === 'celebration') {
        return `${plan.label} · ${plan.event_credits} event ${plan.event_credits === 1 ? 'credit' : 'credits'} left`;
    }

    if (plan.expires_at) {
        const date = new Date(plan.expires_at).toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });

        return `${plan.label} · ${plan.active ? 'active until' : 'ended on'} ${date}`;
    }

    return `${plan.label} plan`;
}
