import { Head, Link, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowUpRight,
    CalendarDays,
    Check,
    ChevronLeft,
    CreditCard,
    Lock,
    MapPin,
    MessageCircle,
    Plus,
    Receipt,
    RefreshCw,
    Sparkles,
    UserCog,
    Users,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

import { PaymentRow } from '@/components/billing/payment-row';
import { PurchaseDialog } from '@/components/billing/purchase-dialog';
import { ConfirmBar, useConfirm } from '@/components/shared/confirm-bar';
import { FormField } from '@/components/shared/form-field';
import { Section, Stepper } from '@/components/shared/form-section';
import { PageHeader } from '@/components/shared/page-header';
import { SectionLabel } from '@/components/shared/section-label';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { plans } from '@/components/web/home/content';
import ClientLayout from '@/layouts/client-layout';
import { formatDate, formatMoney } from '@/lib/format';
import { PLAN_FEATURE_LABELS, planSummary, useClientPlan } from '@/lib/plans';
import { cn } from '@/lib/utils';
import {
    details as detailsRoute,
    enterpriseRenewal,
    eventCredits,
    subscribe,
} from '@/routes/client/billing';
import {
    destroy as planChangeDestroy,
    store as planChangeStore,
} from '@/routes/client/billing/plan-change';
import {
    destroy as destroyCard,
    store as storeCard,
    update as updateCard,
} from '@/routes/client/billing/cards';
import {
    index as paymentsIndex,
    show as showPayment,
} from '@/routes/client/billing/payments';
import { update as updateSubscription } from '@/routes/client/billing/subscription';
import { edit as profileEdit } from '@/routes/client/profile';
import type {
    BillingDetails,
    BillingInterval,
    ClientPlanUsage,
    PaymentCard,
    Payment,
    PlanFeature,
    PriceCatalogue,
    Subscription,
} from '@/types';

type Billing = {
    subscription: Subscription | null;
    cards: PaymentCard[];
    payments: Payment[];
    details: BillingDetails | null;
    /** No PayHere credentials: payments complete on a local test page. */
    mock: boolean;
};

/** What the plan picker opens: Business (with its interval) or Celebration events. */
type Buying =
    | { kind: 'business'; interval: BillingInterval }
    | { kind: 'events' }
    | { kind: 'enterprise' }
    | null;

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

export default function Membership({
    prices,
    billing,
}: {
    prices: PriceCatalogue;
    extraGuestsBlock: number;
    /** null: the user can't manage billing (members without the permission). */
    billing: Billing | null;
}) {
    const plan = useClientPlan();
    const [buying, setBuying] = useState<Buying>(null);

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
                description="Your plan, what you’ve used, what’s included and how you pay."
            />

            {plan && (
                <div className="flex max-w-4xl min-w-0 flex-col gap-6 pt-1 pb-6">
                    <MembershipCard plan={plan} />
                    {billing?.mock && (
                        <p className="rounded-xl bg-warning-muted px-3.5 py-2.5 text-xs font-semibold text-warning">
                            Test mode: PayHere isn’t configured, so payments
                            complete on a local test page and no money moves.
                        </p>
                    )}
                    {billing?.subscription && (
                        <SubscriptionCard
                            subscription={billing.subscription}
                            prices={prices}
                            onResubscribe={(interval) =>
                                setBuying({ kind: 'business', interval })
                            }
                        />
                    )}
                    {plan.plan === 'enterprise' && (
                        <EnterprisePlan
                            current={plan}
                            canBuy={billing !== null}
                            onRenew={() => setBuying({ kind: 'enterprise' })}
                        />
                    )}
                    <Usage
                        plan={plan}
                        onBuyEvents={
                            billing && plan.plan === 'celebration'
                                ? () => setBuying({ kind: 'events' })
                                : undefined
                        }
                    />
                    <Included plan={plan} />
                    {plan.plan !== 'enterprise' && (
                        <Plans
                            current={plan}
                            prices={prices}
                            canBuy={billing !== null}
                            onBuy={setBuying}
                        />
                    )}
                    {billing && (
                        <>
                            <Cards cards={billing.cards} />
                            <Details details={billing.details} />
                            <History payments={billing.payments} />
                        </>
                    )}
                </div>
            )}

            <BusinessDialog
                key={buying?.kind === 'business' ? buying.interval : 'closed'}
                open={buying?.kind === 'business'}
                initialInterval={
                    buying?.kind === 'business' ? buying.interval : 'monthly'
                }
                prices={prices}
                onClose={() => setBuying(null)}
            />
            <EventsDialog
                open={buying?.kind === 'events'}
                prices={prices}
                onClose={() => setBuying(null)}
            />
            {plan && (
                <EnterpriseDialog
                    open={buying?.kind === 'enterprise'}
                    current={plan}
                    onClose={() => setBuying(null)}
                />
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
                        href="#plans"
                        className="inline-flex items-center gap-1 rounded-full bg-primary px-3.5 py-1.5 text-xs font-bold text-primary-foreground"
                    >
                        <Sparkles className="size-3.5" /> Upgrade
                    </a>
                )}
            </div>
        </section>
    );
}

function Usage({
    plan,
    onBuyEvents,
}: {
    plan: ClientPlanUsage;
    /** Celebration: buy more event credits (consumables, not a renewal). */
    onBuyEvents?: () => void;
}) {
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
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <UsageTile
                    icon={CalendarDays}
                    label="Events"
                    {...events}
                    action={
                        onBuyEvents && (
                            <Button
                                size="sm"
                                variant="outline"
                                className="self-start"
                                onClick={onBuyEvents}
                            >
                                <Plus className="size-4" /> Buy more events
                            </Button>
                        )
                    }
                />
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
                <UsageTile
                    icon={UserCog}
                    label="Team"
                    value={
                        plan.users_allowed === null
                            ? `${plan.users_used}`
                            : `${plan.users_used} of ${plan.users_allowed}`
                    }
                    caption={
                        plan.users_allowed === null
                            ? 'people sign in · no limit'
                            : 'people can sign in to this account'
                    }
                    progress={
                        plan.users_allowed === null
                            ? undefined
                            : plan.users_used / plan.users_allowed
                    }
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
    action,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    caption: string;
    progress?: number;
    action?: ReactNode;
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
            {action}
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

/** Where each plan sits, to tell an upgrade from a downgrade. */
const RANK: Record<string, number> = {
    starter: 0,
    celebration: 1,
    business: 2,
    enterprise: 3,
};

/**
 * The other plans, with what each costs now (staff set the prices). An active
 * plan is never renewed early: its card only says so. From a running Business
 * plan the cheaper plans are downgrades that start when Business ends.
 */
function Plans({
    current,
    prices,
    canBuy,
    onBuy,
}: {
    current: ClientPlanUsage;
    prices: PriceCatalogue;
    canBuy: boolean;
    onBuy: (buying: Buying) => void;
}) {
    const { request, ask, cancel } = useConfirm();
    const businessRunning =
        current.plan === 'business' && current.active && !!current.expires_at;
    const ends = current.expires_at ? formatDate(current.expires_at) : '';

    const price = (id: string) =>
        id === 'business'
            ? {
                  value: formatMoney(prices.business_monthly.amount),
                  unit: `per month · or ${formatMoney(prices.business_yearly.amount)} a year`,
              }
            : id === 'celebration'
              ? {
                    value: formatMoney(prices.celebration_event.amount),
                    unit: 'per event',
                }
              : id === 'starter'
                ? { value: 'Free', unit: 'free forever' }
                : { value: 'Custom', unit: 'talk to us' };

    const downgrade = (id: 'celebration' | 'starter', name: string) =>
        ask({
            title: `Move to ${name} when Business ends on ${ends}? Auto-renew turns off.`,
            confirmLabel: 'Downgrade',
            onConfirm: () =>
                router.post(
                    planChangeStore.url(),
                    { plan: id },
                    { preserveScroll: true },
                ),
        });

    const action = (id: string, name: string) => {
        const isCurrent = id === current.plan;

        if (id === 'enterprise') {
            return (
                <a href="/#pricing" className="text-xs font-semibold text-link">
                    Talk to us
                </a>
            );
        }

        if (!canBuy) {
            return null;
        }

        // The current plan: nothing to buy while it runs; Business is renewed once it ended.
        if (isCurrent) {
            return id === 'business' && !current.active ? (
                <Button
                    size="sm"
                    onClick={() =>
                        onBuy({ kind: 'business', interval: 'monthly' })
                    }
                >
                    Renew Business
                </Button>
            ) : null;
        }

        if (businessRunning) {
            if (current.scheduled_plan === id) {
                return (
                    <div className="flex flex-col gap-1.5">
                        <span className="text-xs font-semibold text-warning">
                            Starts {ends}
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.delete(planChangeDestroy.url(), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Keep Business
                        </Button>
                    </div>
                );
            }

            return id === 'celebration' || id === 'starter' ? (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => downgrade(id, name)}
                >
                    Downgrade
                </Button>
            ) : null;
        }

        const upgrade = RANK[id] > RANK[current.plan];

        if (id === 'business') {
            return (
                <Button
                    size="sm"
                    onClick={() =>
                        onBuy({ kind: 'business', interval: 'monthly' })
                    }
                >
                    {upgrade ? 'Upgrade' : 'Switch to Business'}
                </Button>
            );
        }

        if (id === 'celebration') {
            return (
                <Button
                    size="sm"
                    variant={upgrade ? 'default' : 'outline'}
                    onClick={() => onBuy({ kind: 'events' })}
                >
                    {upgrade ? 'Upgrade' : 'Switch to Celebration'}
                </Button>
            );
        }

        // Starter is free and doesn't need a purchase; there's nothing to downgrade from here.
        return null;
    };

    return (
        <section
            id="plans"
            className="relative flex scroll-mt-20 flex-col gap-2.5"
        >
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
                    const isCurrent = item.id === current.plan;
                    const { value, unit } = price(item.id);

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
                                        {current.active ? 'Your plan' : 'Ended'}
                                    </span>
                                )}
                            </div>
                            <div>
                                <div className="text-xl font-bold tabular-nums">
                                    {value}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {unit}
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
                            {isCurrent &&
                                current.active &&
                                current.scheduled_plan && (
                                    <span className="text-xs font-semibold text-warning">
                                        Ends {ends}, then{' '}
                                        {current.scheduled_plan_label}
                                    </span>
                                )}
                            {action(item.id, item.name)}
                        </div>
                    );
                })}
            </div>
            <p className="px-1 text-xs text-muted-foreground">
                {!canBuy
                    ? 'Ask the account owner to change the plan, or for the “Manage billing” permission.'
                    : businessRunning
                      ? 'A downgrade starts when Business ends; you keep everything until then.'
                      : 'Payments go through PayHere. Business renews on its own until you turn auto-renew off.'}
            </p>
            <ConfirmBar request={request} onCancel={cancel} />
        </section>
    );
}

/**
 * A custom (Enterprise) plan: its details, and once it has ended, a renewal
 * at the price and length staff agreed. It can't be swapped for another plan here.
 */
function EnterprisePlan({
    current,
    canBuy,
    onRenew,
}: {
    current: ClientPlanUsage;
    canBuy: boolean;
    onRenew: () => void;
}) {
    const months = current.renewal_months ?? 0;
    const terms =
        current.renewal_price !== null && months > 0
            ? `${formatMoney(current.renewal_price)} for ${months} ${months === 1 ? 'month' : 'months'}`
            : null;

    return (
        <Section
            icon={Sparkles}
            title="Enterprise plan"
            description={
                current.active
                    ? current.expires_at
                        ? `Active until ${formatDate(current.expires_at)}. Arranged with our team.`
                        : 'Arranged with our team; no end date.'
                    : `Ended on ${formatDate(current.expires_at)}.`
            }
            aside={
                <StatusBadge
                    status={current.active ? 'active' : 'expired'}
                    label={current.active ? 'Active' : 'Ended'}
                />
            }
        >
            <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <PlanDetail label="Events" value="Unlimited" />
                <PlanDetail label="Guests per event" value="Unlimited" />
                <PlanDetail
                    label="Team"
                    value={
                        current.users_allowed === null
                            ? 'No limit'
                            : `${current.users_allowed} people`
                    }
                />
                <PlanDetail label="Renewal" value={terms ?? 'With our team'} />
            </dl>
            {!current.active && (
                <div className="mt-4 flex flex-wrap items-center gap-3 border-t border-border pt-4 motion-safe:animate-fade-in">
                    {current.can_renew_online && canBuy ? (
                        <Button onClick={onRenew}>Renew for {terms}</Button>
                    ) : current.can_renew_online ? (
                        <p className="text-sm text-muted-foreground">
                            Ask the account owner to renew the plan.
                        </p>
                    ) : (
                        <a
                            href="/#pricing"
                            className="text-sm font-semibold text-link"
                        >
                            Contact us to renew
                        </a>
                    )}
                </div>
            )}
        </Section>
    );
}

function PlanDetail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-semibold">{value}</dd>
        </div>
    );
}

function EnterpriseDialog({
    open,
    current,
    onClose,
}: {
    open: boolean;
    current: ClientPlanUsage;
    onClose: () => void;
}) {
    const months = current.renewal_months ?? 0;

    return (
        <PurchaseDialog
            open={open}
            onClose={onClose}
            title="Renew Enterprise"
            description={`${months} ${months === 1 ? 'month' : 'months'} from today, with the same features and team.`}
            url={enterpriseRenewal.url()}
            amount={current.renewal_price}
        />
    );
}

function BusinessDialog({
    open,
    initialInterval,
    prices,
    onClose,
}: {
    open: boolean;
    initialInterval: BillingInterval;
    prices: PriceCatalogue;
    onClose: () => void;
}) {
    const [interval, setInterval] = useState<BillingInterval>(initialInterval);
    const price =
        interval === 'monthly'
            ? prices.business_monthly
            : prices.business_yearly;
    const saving =
        prices.business_monthly.amount * 12 - prices.business_yearly.amount;

    return (
        <PurchaseDialog
            open={open}
            onClose={onClose}
            title="Business plan"
            description="Unlimited events, renews on its own. Cancel any time."
            url={subscribe.url()}
            data={{ interval }}
            amount={price.amount}
            currency={price.currency}
            savesCard
        >
            <SegmentedControl
                label="Billing period"
                value={interval}
                onChange={setInterval}
                items={[
                    { value: 'monthly', label: 'Monthly' },
                    { value: 'yearly', label: 'Yearly' },
                ]}
            />
            <p className="text-xs text-muted-foreground">
                {interval === 'monthly'
                    ? `${formatMoney(price.amount)} every month.`
                    : `${formatMoney(price.amount)} every year${saving > 0 ? `: you save ${formatMoney(saving)}` : ''}.`}{' '}
                We remind you before yearly renewals.
            </p>
        </PurchaseDialog>
    );
}

function EventsDialog({
    open,
    prices,
    onClose,
}: {
    open: boolean;
    prices: PriceCatalogue;
    onClose: () => void;
}) {
    const [quantity, setQuantity] = useState(1);
    const price = prices.celebration_event;

    return (
        <PurchaseDialog
            open={open}
            onClose={onClose}
            title="Celebration events"
            description="Each new event uses one. They don’t expire."
            url={eventCredits.url()}
            data={{ quantity }}
            amount={price.amount * quantity}
            currency={price.currency}
        >
            <div className="flex items-center justify-between rounded-2xl bg-raised px-4 py-3">
                <span className="text-sm font-semibold">
                    {quantity} {quantity === 1 ? 'event' : 'events'}
                </span>
                <Stepper
                    label="Events"
                    value={quantity}
                    min={1}
                    max={10}
                    onChange={setQuantity}
                />
            </div>
        </PurchaseDialog>
    );
}

/** The Business subscription: when it renews (or ends), auto-renew, monthly ↔ yearly. */
function SubscriptionCard({
    subscription,
    prices,
    onResubscribe,
}: {
    subscription: Subscription;
    prices: PriceCatalogue;
    onResubscribe: (interval: BillingInterval) => void;
}) {
    const { request, ask, cancel } = useConfirm();
    const end = formatDate(subscription.current_period_end);
    const renewing = subscription.status === 'active' && subscription.running;
    const next = subscription.next_interval ?? subscription.interval;
    const nextPrice =
        next === 'monthly' ? prices.business_monthly : prices.business_yearly;
    const other: BillingInterval = next === 'monthly' ? 'yearly' : 'monthly';

    const change = (data: Record<string, string>) =>
        router.patch(updateSubscription.url(), data, { preserveScroll: true });

    const line = renewing
        ? `Renews on ${end} for ${formatMoney(nextPrice.amount, nextPrice.currency)}${subscription.card ? ` · ${subscription.card.label}` : ''}`
        : subscription.running
          ? `Auto-renew is off. Business stays until ${end}.`
          : subscription.ended_reason === 'renewal_failed'
            ? `The renewal was declined. Business ends on ${end}.`
            : `Ended on ${end}.`;

    return (
        <div className="relative">
            <Section
                icon={RefreshCw}
                title={`Business · ${subscription.interval === 'monthly' ? 'Monthly' : 'Yearly'}`}
                description={line}
                aside={
                    <StatusBadge
                        status={subscription.status}
                        label={subscription.status_label}
                    />
                }
            >
                {subscription.next_interval && renewing && (
                    <p className="mb-3 text-xs text-muted-foreground">
                        Switches to {subscription.next_interval} billing on{' '}
                        {end}.
                    </p>
                )}
                <div className="flex flex-wrap gap-2">
                    {renewing && (
                        <>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    change({
                                        action: 'interval',
                                        interval: other,
                                    })
                                }
                            >
                                Switch to {other}
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() =>
                                    ask({
                                        title: `Turn off auto-renew? Business stays until ${end}.`,
                                        confirmLabel: 'Turn off',
                                        onConfirm: () =>
                                            change({ action: 'cancel' }),
                                    })
                                }
                            >
                                Turn off auto-renew
                            </Button>
                        </>
                    )}
                    {subscription.status === 'canceled' &&
                        subscription.running && (
                            <Button
                                size="sm"
                                onClick={() => change({ action: 'resume' })}
                            >
                                Turn auto-renew back on
                            </Button>
                        )}
                    {!subscription.running ||
                    subscription.status === 'expired' ? (
                        <Button
                            size="sm"
                            onClick={() => onResubscribe(subscription.interval)}
                        >
                            Subscribe again
                        </Button>
                    ) : null}
                </div>
            </Section>
            <ConfirmBar request={request} onCancel={cancel} />
        </div>
    );
}

/** Saved cards: the default one pays for one-click purchases and renewals. */
function Cards({ cards }: { cards: PaymentCard[] }) {
    const { request, ask, cancel } = useConfirm();
    const [adding, setAdding] = useState(false);
    const visible = cards.filter((card) => !card.removed);

    return (
        <div className="relative">
            <Section
                icon={CreditCard}
                title="Payment methods"
                description="Cards are saved by PayHere; we only keep a token and the last four digits."
            >
                <div className="flex flex-col gap-2">
                    {visible.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No saved cards yet.
                        </p>
                    )}
                    {visible.map((card) => (
                        <div
                            key={card.id}
                            className="flex items-center gap-3 rounded-xl border border-border px-3 py-2.5"
                        >
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-raised">
                                <CreditCard className="size-4" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2 text-sm font-semibold">
                                    {card.label}
                                    {card.is_default && (
                                        <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary uppercase">
                                            Default
                                        </span>
                                    )}
                                    {card.is_expired && (
                                        <StatusBadge
                                            status="expired"
                                            label="Expired"
                                        />
                                    )}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {card.card_holder_name}
                                    {card.expiry && ` · expires ${card.expiry}`}
                                </div>
                            </div>
                            {!card.is_default && !card.is_expired && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        router.patch(
                                            updateCard.url(card),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Make default
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-destructive"
                                onClick={() =>
                                    ask({
                                        title: `Remove ${card.label}?`,
                                        confirmLabel: 'Remove',
                                        onConfirm: () =>
                                            router.delete(
                                                destroyCard.url(card),
                                                { preserveScroll: true },
                                            ),
                                    })
                                }
                            >
                                Remove
                            </Button>
                        </div>
                    ))}
                    <Button
                        variant="outline"
                        className="self-start"
                        disabled={adding}
                        onClick={() => {
                            setAdding(true);
                            router.post(
                                storeCard.url(),
                                {},
                                { onFinish: () => setAdding(false) },
                            );
                        }}
                    >
                        <Plus className="size-4" /> Add a card
                    </Button>
                </div>
            </Section>
            <ConfirmBar request={request} onCancel={cancel} />
        </div>
    );
}

/** The billing contact PayHere asks for at checkout. */
function Details({ details }: { details: BillingDetails | null }) {
    const [data, setData] = useState<BillingDetails>(details ?? {});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.put(detailsRoute.url(), data, {
            preserveScroll: true,
            onSuccess: () => setErrors({}),
            onError: setErrors,
            onFinish: () => setProcessing(false),
        });
    };

    const field = (
        key: keyof BillingDetails,
        label: string,
        autoComplete: string,
    ) => (
        <FormField label={label} htmlFor={`billing-${key}`} error={errors[key]}>
            <Input
                id={`billing-${key}`}
                autoComplete={autoComplete}
                value={data[key] ?? ''}
                onChange={(e) => setData({ ...data, [key]: e.target.value })}
            />
        </FormField>
    );

    return (
        <Section
            icon={MapPin}
            title="Billing details"
            description="PayHere asks for these with every checkout."
        >
            <form
                onSubmit={submit}
                className="grid gap-3 sm:grid-cols-2"
                noValidate
            >
                {field('phone', 'Phone', 'tel')}
                {field('city', 'City', 'address-level2')}
                <div className="sm:col-span-2">
                    {field('address', 'Address', 'street-address')}
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    className="justify-self-start"
                    disabled={processing}
                >
                    Save details
                </Button>
            </form>
        </Section>
    );
}

function History({ payments }: { payments: Payment[] }) {
    return (
        <section className="flex flex-col gap-2.5">
            <div className="flex items-end justify-between gap-2">
                <SectionLabel>Payments</SectionLabel>
                {payments.length > 0 && (
                    <Link
                        href={paymentsIndex.url()}
                        className="text-xs font-semibold text-link"
                    >
                        All payments
                    </Link>
                )}
            </div>
            {payments.length === 0 ? (
                <div className="flex items-center gap-3 rounded-2xl bg-card px-4 py-4 text-sm text-muted-foreground shadow-card">
                    <Receipt className="size-4" /> No payments yet.
                </div>
            ) : (
                <div className="divide-y divide-border overflow-hidden rounded-2xl bg-card shadow-card">
                    {payments.map((payment) => (
                        <PaymentRow
                            key={payment.id}
                            payment={payment}
                            href={showPayment.url(payment)}
                        />
                    ))}
                </div>
            )}
        </section>
    );
}
