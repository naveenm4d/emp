import { Head, Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowUpRight,
    CalendarDays,
    Check,
    ChevronLeft,
    Lock,
    MessageCircle,
    Sparkles,
    Users,
} from 'lucide-react';

import { PageHeader } from '@/components/shared/page-header';
import { SectionLabel } from '@/components/shared/section-label';
import { formatLkr, plans } from '@/components/web/home/content';
import ClientLayout from '@/layouts/client-layout';
import { PLAN_FEATURE_LABELS, planSummary, useClientPlan } from '@/lib/plans';
import { cn } from '@/lib/utils';
import { edit as profileEdit } from '@/routes/client/profile';
import type { ClientPlanUsage, PlanFeature } from '@/types';

/** "Active until 12 Oct 2027" / "2 event credits left" (the summary without the plan name). */
function statusLine(plan: ClientPlanUsage): string {
    const [, ...rest] = planSummary(plan).split(' · ');
    const line = rest.join(' · ');

    return line ? line.charAt(0).toUpperCase() + line.slice(1) : 'Your plan';
}

function statusChip(plan: ClientPlanUsage): { label: string; live: boolean } {
    if (plan.plan === 'starter') {
        return { label: 'Free forever', live: true };
    }

    return plan.active
        ? { label: 'Active', live: true }
        : { label: 'Ended', live: false };
}

export default function Membership() {
    const plan = useClientPlan();

    return (
        <ClientLayout>
            <Head title="Membership" />
            <PageHeader
                title="Membership"
                className="max-w-4xl"
                eyebrow={
                    <Link
                        href={profileEdit.url()}
                        className="inline-flex items-center gap-0.5 md:hidden"
                    >
                        <ChevronLeft className="size-4" /> Account
                    </Link>
                }
                description="Your plan, what you’ve used and what’s included."
            />

            {plan && (
                <div className="flex max-w-4xl min-w-0 flex-col gap-6 pt-1 pb-6">
                    <MembershipCard plan={plan} />
                    <Usage plan={plan} />
                    <Included plan={plan} />
                    <OtherPlans current={plan.plan} />
                </div>
            )}
        </ClientLayout>
    );
}

function MembershipCard({ plan }: { plan: ClientPlanUsage }) {
    const chip = statusChip(plan);
    const tagline = plans.find((item) => item.id === plan.plan)?.tagline;

    return (
        <section className="relative isolate overflow-hidden rounded-3xl bg-strong p-6 text-strong-foreground shadow-card md:p-8">
            <div
                aria-hidden
                className="absolute -top-24 -right-20 -z-10 size-72 rounded-full bg-primary/35 blur-3xl"
            />
            <div
                aria-hidden
                className="absolute -bottom-28 -left-16 -z-10 size-64 rounded-full bg-link/20 blur-3xl"
            />
            <div className="flex items-start justify-between gap-3">
                <span className="text-[11px] font-bold tracking-[0.2em] uppercase opacity-70">
                    EMP membership
                </span>
                <span
                    className={cn(
                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold',
                        chip.live
                            ? 'bg-success/20 text-success'
                            : 'bg-destructive/20 text-destructive',
                    )}
                >
                    <span
                        className={cn(
                            'size-1.5 rounded-full',
                            chip.live ? 'bg-success' : 'bg-destructive',
                        )}
                    />
                    {chip.label}
                </span>
            </div>
            <h2 className="mt-8 text-4xl font-bold tracking-tight md:text-5xl">
                {plan.label}
            </h2>
            {tagline && <p className="mt-1.5 text-sm opacity-70">{tagline}</p>}
            <div className="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-current/15 pt-4">
                <span className="text-sm font-semibold">
                    {statusLine(plan)}
                </span>
                {plan.plan !== 'enterprise' && (
                    <a
                        href="/#pricing"
                        className="inline-flex items-center gap-1 rounded-full bg-primary px-3.5 py-1.5 text-xs font-bold text-primary-foreground"
                    >
                        <Sparkles className="size-3.5" /> Upgrade
                    </a>
                )}
            </div>
        </section>
    );
}

function Usage({ plan }: { plan: ClientPlanUsage }) {
    const events =
        plan.events_allowed !== null
            ? {
                  value: `${plan.events_used} of ${plan.events_allowed}`,
                  caption: 'events used',
                  progress: plan.events_used / plan.events_allowed,
              }
            : plan.plan === 'celebration'
              ? {
                    value: String(plan.event_credits),
                    caption: `event ${plan.event_credits === 1 ? 'credit' : 'credits'} left · one per new event`,
                }
              : {
                    value: 'Unlimited',
                    caption: `events · ${plan.events_used} created so far`,
                };

    return (
        <section className="flex flex-col gap-2.5">
            <SectionLabel>Usage</SectionLabel>
            {!plan.can_create_event && plan.reason && (
                <div className="flex items-start gap-2.5 rounded-xl border border-warning/30 bg-warning-muted px-3.5 py-3 text-sm">
                    <Lock
                        className="mt-0.5 size-4 shrink-0 text-warning"
                        strokeWidth={2}
                    />
                    {plan.reason}
                </div>
            )}
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <UsageTile icon={CalendarDays} label="Events" {...events} />
                <UsageTile
                    icon={Users}
                    label="Guests per event"
                    value={
                        plan.max_guests_per_event?.toLocaleString('en-GB') ??
                        'Unlimited'
                    }
                    caption={
                        plan.allows_extra_guests
                            ? 'Extra guests can be added per event'
                            : 'per event'
                    }
                />
                <UsageTile
                    icon={MessageCircle}
                    label="WhatsApp messages"
                    value={`${plan.message_limits.invitations} + ${plan.message_limits.reminders}`}
                    caption="invitations + reminders per guest"
                />
            </div>
        </section>
    );
}

function UsageTile({
    icon: Icon,
    label,
    value,
    caption,
    progress,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    caption: string;
    progress?: number;
}) {
    return (
        <div className="flex flex-col gap-3 rounded-2xl bg-card p-4 shadow-card">
            <div className="flex items-center gap-2 text-xs font-semibold text-muted-foreground">
                <span className="flex size-7 items-center justify-center rounded-lg bg-raised">
                    <Icon className="size-3.5" strokeWidth={2} />
                </span>
                {label}
            </div>
            <div>
                <div className="text-2xl font-bold tabular-nums">{value}</div>
                <div className="mt-0.5 text-xs text-muted-foreground">
                    {caption}
                </div>
            </div>
            {progress !== undefined && (
                <div className="h-1.5 overflow-hidden rounded-full bg-foreground/6">
                    <div
                        className={cn(
                            'h-full rounded-full',
                            progress >= 1 ? 'bg-warning' : 'bg-primary',
                        )}
                        style={{ width: `${Math.min(1, progress) * 100}%` }}
                    />
                </div>
            )}
        </div>
    );
}

function Included({ plan }: { plan: ClientPlanUsage }) {
    const features = Object.keys(PLAN_FEATURE_LABELS) as PlanFeature[];

    return (
        <section className="flex flex-col gap-2.5">
            <SectionLabel>What’s included</SectionLabel>
            <div className="grid grid-cols-1 gap-2 rounded-2xl bg-card p-2 shadow-card sm:grid-cols-2">
                {features.map((feature) => {
                    const included = plan.features.includes(feature);

                    return (
                        <div
                            key={feature}
                            className={cn(
                                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm',
                                !included && 'text-subtle',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-full',
                                    included
                                        ? 'bg-success-muted text-success'
                                        : 'bg-foreground/6',
                                )}
                            >
                                {included ? (
                                    <Check
                                        className="size-3.5"
                                        strokeWidth={2.5}
                                    />
                                ) : (
                                    <Lock className="size-3" strokeWidth={2} />
                                )}
                            </span>
                            <span className="flex-1 font-medium">
                                {PLAN_FEATURE_LABELS[feature]}
                            </span>
                            {!included && (
                                <span className="text-[11px] font-semibold">
                                    Upgrade
                                </span>
                            )}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function OtherPlans({ current }: { current: ClientPlanUsage['plan'] }) {
    return (
        <section className="flex flex-col gap-2.5">
            <div className="flex items-end justify-between gap-2">
                <SectionLabel>Plans</SectionLabel>
                <a
                    href="/#pricing"
                    className="inline-flex items-center gap-0.5 text-xs font-semibold text-link"
                >
                    Compare all <ArrowUpRight className="size-3.5" />
                </a>
            </div>
            <div className="-mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-3 pb-2 md:mx-0 md:grid md:grid-cols-2 md:overflow-visible md:px-0 lg:grid-cols-4">
                {plans.map((item) => {
                    const isCurrent = item.id === current;

                    return (
                        <div
                            key={item.id}
                            className={cn(
                                'flex w-64 shrink-0 snap-start flex-col gap-3 rounded-2xl bg-card p-4 shadow-card md:w-auto',
                                isCurrent && 'ring-2 ring-primary',
                            )}
                        >
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-sm font-bold">
                                    {item.name}
                                </span>
                                {isCurrent && (
                                    <span className="rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold text-primary-foreground uppercase">
                                        Your plan
                                    </span>
                                )}
                            </div>
                            <div>
                                <div className="text-xl font-bold tabular-nums">
                                    {item.price === null
                                        ? 'Custom'
                                        : item.price.monthly === 0
                                          ? 'Free'
                                          : formatLkr(item.price.monthly)}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {item.unit.monthly}
                                </div>
                            </div>
                            <ul className="flex flex-1 flex-col gap-1.5 text-xs text-muted-foreground">
                                {item.features.slice(0, 3).map((feature) => (
                                    <li key={feature} className="flex gap-1.5">
                                        <Check
                                            className="mt-px size-3.5 shrink-0 text-success"
                                            strokeWidth={2.5}
                                        />
                                        {feature}
                                    </li>
                                ))}
                            </ul>
                            {!isCurrent && (
                                <a
                                    href="/#pricing"
                                    className="text-xs font-semibold text-link"
                                >
                                    Talk to us to switch
                                </a>
                            )}
                        </div>
                    );
                })}
            </div>
            <p className="px-1 text-xs text-muted-foreground">
                Plans are set up by our team once payment is received. There’s
                no online payment yet.
            </p>
        </section>
    );
}
