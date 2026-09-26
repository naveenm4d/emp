import { ArrowRight, Check, ChevronDown, Minus } from 'lucide-react';
import { useState } from 'react';

import { cn } from '@/lib/utils';

import type { Billing, Plan } from './content';
import { comparison, formatLkr, plans } from './content';
import { Reveal, SplitText } from './reveal';

export function Pricing({ dashboardUrl }: { dashboardUrl: string }) {
    const [billing, setBilling] = useState<Billing>('monthly');

    return (
        <section
            id="pricing"
            className="bg-ink text-ivory relative scroll-mt-24 overflow-hidden py-28 sm:py-36"
        >
            <div
                aria-hidden
                className="absolute top-0 left-1/2 size-[1000px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(circle,rgb(176_141_87/0.22),transparent_60%)]"
            />

            <div className="relative mx-auto max-w-6xl px-6">
                <div className="mx-auto max-w-3xl text-center">
                    <Reveal>
                        <p className="text-gold-soft text-sm tracking-[0.3em] uppercase">
                            Pricing
                        </p>
                    </Reveal>
                    <SplitText
                        text="Simple pricing for *every* occasion."
                        className="font-display mt-4 text-[clamp(2.3rem,5vw,4rem)] leading-[1.03] font-medium tracking-tight"
                    />
                    <Reveal delay={200}>
                        <p className="text-ivory/60 mt-5 text-lg">
                            Start free. Pay per event when it’s your big day, or
                            subscribe if you plan events for a living.
                        </p>
                    </Reveal>

                    <Reveal delay={300}>
                        <div
                            role="radiogroup"
                            aria-label="Studio billing period"
                            className="border-ivory/15 bg-ivory/5 relative mt-10 inline-grid grid-cols-2 rounded-full border p-1 text-sm"
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'bg-ivory absolute inset-y-1 left-1 w-[calc(50%-4px)] rounded-full transition-transform duration-500 ease-[cubic-bezier(.2,.8,.2,1)]',
                                    billing === 'yearly' && 'translate-x-full',
                                )}
                            />
                            {(['monthly', 'yearly'] as const).map((option) => (
                                <button
                                    key={option}
                                    type="button"
                                    role="radio"
                                    aria-checked={billing === option}
                                    onClick={() => setBilling(option)}
                                    className={cn(
                                        'relative z-10 rounded-full px-6 py-2 font-medium transition-colors duration-500',
                                        billing === option
                                            ? 'text-ink'
                                            : 'text-ivory/70',
                                    )}
                                >
                                    {option === 'monthly'
                                        ? 'Monthly'
                                        : 'Yearly'}
                                    {option === 'yearly' && (
                                        <span
                                            className={cn(
                                                'ml-1.5 text-xs',
                                                billing === option
                                                    ? 'text-gold-deep'
                                                    : 'text-gold-soft',
                                            )}
                                        >
                                            2 months free
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>
                    </Reveal>
                </div>

                <div className="mt-16 grid items-stretch gap-5 lg:grid-cols-3">
                    {plans.map((plan, i) => (
                        <Reveal
                            key={plan.id}
                            delay={i * 120}
                            className={cn(plan.featured && 'lg:-my-4')}
                        >
                            <PlanCard
                                plan={plan}
                                billing={billing}
                                dashboardUrl={dashboardUrl}
                            />
                        </Reveal>
                    ))}
                </div>

                <Reveal>
                    <p className="text-ivory/45 mt-10 text-center text-sm">
                        Prices in Sri Lankan rupees. Some premium designer
                        templates carry a one-time price, shown before you pick
                        them.
                    </p>
                </Reveal>

                <Comparison />
            </div>
        </section>
    );
}

function PlanCard({
    plan,
    billing,
    dashboardUrl,
}: {
    plan: Plan;
    billing: Billing;
    dashboardUrl: string;
}) {
    const price = plan.price[billing];
    const changes = plan.price.monthly !== plan.price.yearly;

    return (
        <article
            className={cn(
                'relative flex h-full flex-col rounded-[1.75rem] p-8 transition-transform duration-500 hover:-translate-y-1',
                plan.featured
                    ? 'gold-ring shadow-[0_40px_120px_-40px_rgb(176_141_87/0.55)] lg:py-12'
                    : 'border-ivory/10 bg-ivory/[0.03] border',
            )}
        >
            {plan.featured && (
                <span className="bg-gold text-ink absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full px-3.5 py-1 text-xs font-semibold tracking-wide">
                    Most loved
                </span>
            )}
            <h3 className="font-display text-2xl">{plan.name}</h3>
            <p className="text-ivory/55 mt-1 text-sm">{plan.tagline}</p>

            <div className="mt-8 flex flex-wrap items-baseline gap-x-2 overflow-hidden">
                <span
                    key={changes ? billing : 'fixed'}
                    className="animate-slide-up font-display text-[2.6rem] leading-none font-medium tracking-tight whitespace-nowrap tabular-nums xl:text-5xl"
                >
                    {formatLkr(price)}
                </span>
                <span className="text-ivory/50 text-sm">
                    {plan.unit[billing]}
                </span>
            </div>
            {changes && (
                <p className="text-gold-soft mt-1 h-5 text-xs">
                    {billing === 'yearly'
                        ? `${formatLkr(Math.round(price / 12))}/mo, billed yearly`
                        : 'or save 2 months with yearly billing'}
                </p>
            )}

            <a
                href={dashboardUrl}
                className={cn(
                    'group mt-8 inline-flex items-center justify-center gap-2 rounded-full py-3.5 text-sm font-medium transition-all',
                    plan.featured
                        ? 'bg-gold text-ink hover:bg-gold-soft'
                        : 'bg-ivory/10 text-ivory hover:bg-ivory hover:text-ink',
                )}
            >
                {plan.cta}
                <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
            </a>

            <ul className="border-ivory/10 mt-8 space-y-3 border-t pt-8 text-sm">
                {plan.features.map((feature) => (
                    <li key={feature} className="flex gap-3">
                        <Check className="text-gold mt-0.5 size-4 shrink-0" />
                        <span className="text-ivory/80">{feature}</span>
                    </li>
                ))}
            </ul>
        </article>
    );
}

function Comparison() {
    return (
        <details className="group mx-auto mt-14 max-w-4xl">
            <summary className="border-ivory/15 text-ivory/80 hover:bg-ivory/5 mx-auto flex w-fit cursor-pointer items-center gap-2 rounded-full border px-5 py-2.5 text-sm transition-colors">
                Compare all features
                <ChevronDown className="size-4 transition-transform duration-300 group-open:rotate-180" />
            </summary>
            <div className="faq-body">
                <div className="overflow-hidden">
                    <div className="border-ivory/10 mt-8 overflow-x-auto rounded-2xl border">
                        <table className="w-full min-w-[560px] text-left text-sm">
                            <thead>
                                <tr className="border-ivory/10 text-ivory/60 border-b">
                                    <th
                                        scope="col"
                                        className="px-5 py-4 font-normal"
                                    >
                                        <span className="sr-only">Feature</span>
                                    </th>
                                    {plans.map((plan) => (
                                        <th
                                            key={plan.id}
                                            scope="col"
                                            className="font-display text-ivory px-5 py-4 text-base font-normal"
                                        >
                                            {plan.name}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-ivory/5 divide-y">
                                {comparison.map((row) => (
                                    <tr key={row.label}>
                                        <th
                                            scope="row"
                                            className="text-ivory/70 px-5 py-3.5 font-normal"
                                        >
                                            {row.label}
                                        </th>
                                        {row.values.map((value, i) => (
                                            <td key={i} className="px-5 py-3.5">
                                                {value === true ? (
                                                    <Check
                                                        className="text-gold size-4"
                                                        aria-label="Included"
                                                    />
                                                ) : value === false ? (
                                                    <Minus
                                                        className="text-ivory/25 size-4"
                                                        aria-label="Not included"
                                                    />
                                                ) : (
                                                    <span className="text-ivory/85">
                                                        {value}
                                                    </span>
                                                )}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </details>
    );
}
