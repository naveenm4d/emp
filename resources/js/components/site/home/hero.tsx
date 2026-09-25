import { ArrowDown, ArrowRight, Check } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

import { usePrefersReducedMotion, useScrollY, useTicker } from './hooks';
import { InvitationCard } from './invitation-card';
import { PhoneMockup } from './phone-mockup';
import { Reveal, SplitText } from './reveal';

export function Hero({ dashboardUrl }: { dashboardUrl: string }) {
    const y = useScrollY();
    const reduced = usePrefersReducedMotion();
    // 0 sealed · 1 opening · 2 invitation · 3 accepted
    const stage = useTicker(4, 2000, !reduced);
    const shown = reduced ? 2 : stage;
    const parallax = reduced ? 0 : Math.min(y, 800);

    return (
        <section
            id="top"
            className="grain relative isolate overflow-hidden pt-32 pb-24 sm:pt-40 lg:pb-32"
        >
            {/* ambient glow */}
            <div
                aria-hidden
                className="absolute top-1/3 left-1/2 -z-10 size-[900px] rounded-full bg-[radial-gradient(circle,rgb(217_194_154/0.55)_0%,rgb(247_243_236/0)_62%)]"
                style={{
                    transform: `translate(-50%, calc(-50% + ${parallax * 0.25}px))`,
                }}
            />

            <div className="mx-auto grid max-w-6xl items-center gap-16 px-6 lg:grid-cols-[1.15fr_1fr] lg:gap-8">
                <div className="text-center lg:text-left">
                    <Reveal>
                        <p className="border-gold/30 text-gold-deep inline-flex items-center gap-2 rounded-full border bg-white/40 px-3 py-1 text-xs tracking-wide backdrop-blur">
                            <span className="bg-gold size-1.5 rounded-full" />
                            Digital invitations, delivered on WhatsApp
                        </p>
                    </Reveal>

                    <SplitText
                        as="h1"
                        text="Invitations that feel like the *occasion.*"
                        delay={150}
                        className="font-display mt-6 text-[clamp(2.9rem,7.5vw,5.8rem)] leading-[0.98] font-medium tracking-[-0.03em] text-balance"
                    />

                    <Reveal delay={550}>
                        <p className="text-ink/65 mx-auto mt-7 max-w-xl text-lg leading-relaxed lg:mx-0">
                            Design a beautiful invitation in minutes, send it to
                            every guest on WhatsApp, and watch the RSVPs come
                            in. Your guests don’t need an app.
                        </p>
                    </Reveal>

                    <Reveal delay={700}>
                        <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row lg:justify-start">
                            <a
                                href={dashboardUrl}
                                className="group bg-ink text-ivory hover:bg-gold-deep inline-flex items-center gap-2 rounded-full px-7 py-4 text-[15px] font-medium shadow-[0_20px_40px_-20px_rgb(22_19_15/0.6)] transition-all hover:-translate-y-0.5"
                            >
                                Create your invitation
                                <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                            </a>
                            <a
                                href="#how-it-works"
                                className="group text-ink/80 hover:text-ink inline-flex items-center gap-2 rounded-full px-6 py-4 text-[15px] font-medium transition-colors"
                            >
                                See how it works
                                <ArrowDown className="size-4 transition-transform group-hover:translate-y-0.5" />
                            </a>
                        </div>
                    </Reveal>

                    <Reveal delay={850}>
                        <p className="text-ink/50 mt-8 text-sm">
                            Free for your first event · No card needed
                        </p>
                    </Reveal>
                </div>

                <div
                    className="relative mx-auto"
                    style={{
                        transform: `translate3d(0, ${parallax * -0.08}px, 0)`,
                    }}
                >
                    <Reveal delay={300}>
                        <div className="animate-float">
                            <PhoneMockup>
                                <HeroScreen stage={shown} />
                            </PhoneMockup>
                        </div>
                    </Reveal>

                    {/* floating chips */}
                    <FloatingChip
                        className="top-16 -left-10 sm:-left-24"
                        visible={shown >= 1}
                        title="Delivered"
                        detail="Read 2:14 pm"
                        icon={<DoubleTick />}
                    />
                    <FloatingChip
                        className="-right-6 bottom-28 sm:-right-20"
                        visible={shown === 3}
                        title="Tharushi accepted"
                        detail="+1 guest attending"
                        icon={
                            <span className="bg-gold text-ivory grid size-7 place-items-center rounded-full">
                                <Check className="size-4" />
                            </span>
                        }
                    />
                </div>
            </div>
        </section>
    );
}

function HeroScreen({ stage }: { stage: number }) {
    const opened = stage >= 1;

    return (
        <div className="relative h-full w-full [perspective:900px]">
            <div
                className={cn(
                    'absolute inset-0 transition-all duration-1000 ease-out',
                    stage >= 2
                        ? 'scale-100 opacity-100'
                        : 'scale-[0.94] opacity-60',
                )}
            >
                <InvitationCard variant="classic" />
            </div>

            {/* envelope: covers the screen, flap flips up, then it slides away */}
            <div
                aria-hidden
                className={cn(
                    'absolute inset-0 z-10 overflow-hidden bg-gradient-to-b from-[#eadbbd] to-[#e2cfaa] transition-transform duration-[1100ms] ease-[cubic-bezier(.6,0,.3,1)] [perspective:900px]',
                    stage >= 2 ? 'translate-y-[105%]' : 'translate-y-0',
                )}
            >
                <svg
                    viewBox="0 0 100 100"
                    preserveAspectRatio="none"
                    className="absolute inset-0 h-full w-full"
                >
                    <path
                        d="M0 100 L50 58 L100 100"
                        fill="rgb(138 106 58 / 0.07)"
                        stroke="rgb(138 106 58 / 0.3)"
                        strokeWidth="0.4"
                        vectorEffect="non-scaling-stroke"
                    />
                </svg>
                {/* flap */}
                <div
                    className={cn(
                        'absolute inset-x-0 top-0 z-20 h-[58%] origin-top [filter:drop-shadow(0_10px_12px_rgb(22_19_15/0.22))] transition-transform duration-700 ease-in-out',
                        opened && '[transform:rotateX(180deg)]',
                    )}
                >
                    <div className="h-full w-full bg-gradient-to-b from-[#dcc49a] to-[#c9a874] [clip-path:polygon(0_0,100%_0,50%_100%)]" />
                </div>
                {/* wax seal */}
                <div
                    className={cn(
                        'font-display absolute top-[58%] left-1/2 z-30 grid size-14 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-[radial-gradient(circle_at_35%_30%,#b3474f,#6e1f27)] text-xl text-[#f6dcc0] italic shadow-[0_6px_14px_rgb(110_31_39/0.45),inset_0_0_0_3px_rgb(0_0_0/0.12)] transition-all duration-500',
                        opened ? 'scale-0 opacity-0' : 'scale-100 opacity-100',
                    )}
                >
                    e
                </div>
                <p className="absolute inset-x-0 bottom-[16%] z-10 text-center">
                    <span className="text-gold-deep/70 block text-[9px] tracking-[0.35em] uppercase">
                        An invitation for
                    </span>
                    <span className="font-display text-ink/80 mt-1 block text-2xl italic">
                        Tharushi
                    </span>
                </p>
            </div>

            {/* accepted toast */}
            <div
                className={cn(
                    'bg-ink/90 text-ivory absolute inset-x-3 top-11 z-20 flex items-center gap-2.5 rounded-2xl px-3 py-2.5 shadow-xl backdrop-blur transition-all duration-500',
                    stage === 3
                        ? 'translate-y-0 opacity-100'
                        : '-translate-y-4 opacity-0',
                )}
            >
                <span className="bg-gold grid size-7 shrink-0 place-items-center rounded-full">
                    <Check className="size-4" />
                </span>
                <span className="text-[11px] leading-tight">
                    <strong className="block font-semibold">
                        You’re attending!
                    </strong>
                    <span className="text-ivory/70">
                        See you there, Tharushi.
                    </span>
                </span>
            </div>
        </div>
    );
}

function FloatingChip({
    className,
    visible,
    title,
    detail,
    icon,
}: {
    className?: string;
    visible: boolean;
    title: string;
    detail: string;
    icon: ReactNode;
}) {
    return (
        <div
            aria-hidden
            className={cn(
                'absolute z-20 hidden items-center gap-3 rounded-2xl border border-white/60 bg-white/70 py-2.5 pr-4 pl-2.5 shadow-[0_20px_50px_-20px_rgb(22_19_15/0.4)] backdrop-blur-xl transition-all duration-700 sm:flex',
                visible
                    ? 'translate-y-0 scale-100 opacity-100'
                    : 'translate-y-3 scale-95 opacity-0',
                className,
            )}
        >
            {icon}
            <span className="text-xs leading-tight">
                <strong className="text-ink block font-semibold">
                    {title}
                </strong>
                <span className="text-ink/55">{detail}</span>
            </span>
        </div>
    );
}

export function DoubleTick({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'grid size-7 place-items-center rounded-full bg-[#e7f7ee] text-[#34b7f1]',
                className,
            )}
        >
            <svg
                viewBox="0 0 24 24"
                className="size-4"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.2"
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <path d="m2 13 4 4L16 7M11 16l1 1L22 7" />
            </svg>
        </span>
    );
}
