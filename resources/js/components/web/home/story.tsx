import { Check, Image as ImageIcon, MapPin, Music2, Video } from 'lucide-react';
import type { ReactNode } from 'react';
import { useRef } from 'react';

import { cn } from '@/lib/utils';

import { steps } from './content';
import { DoubleTick } from './hero';
import { usePrefersReducedMotion, useScrollProgress } from './hooks';
import { InvitationCard } from './invitation-card';
import { PhoneMockup } from './phone-mockup';
import { Reveal } from './reveal';

/**
 * Apple-style pinned scene: the section is 4 screens tall, the viewport
 * sticks, and scroll position picks which step (and phone screen) shows.
 */
export function Story() {
    const ref = useRef<HTMLElement>(null);
    const progress = useScrollProgress(ref);
    const reduced = usePrefersReducedMotion();
    const active = Math.min(
        steps.length - 1,
        Math.floor(progress * steps.length),
    );

    return (
        <section
            id="how-it-works"
            ref={ref}
            className={cn(
                'bg-ink text-ivory relative',
                !reduced && 'lg:h-[400vh]',
            )}
        >
            {/* Desktop: pinned scrollytelling */}
            <div
                className={cn(
                    'sticky top-0 hidden h-screen overflow-hidden',
                    !reduced && 'lg:block',
                )}
            >
                <div
                    aria-hidden
                    className="absolute top-1/2 right-[12%] size-[640px] -translate-y-1/2 rounded-full bg-[radial-gradient(circle,rgb(176_141_87/0.28),transparent_65%)]"
                />
                <div className="mx-auto grid h-full max-w-6xl grid-cols-[1fr_auto] items-center gap-20 px-6">
                    <div>
                        <p className="text-gold-soft text-sm tracking-[0.3em] uppercase">
                            How it works
                        </p>
                        <h2 className="font-display mt-4 text-6xl leading-[1.02] tracking-tight">
                            From idea to “I’ll be there”
                            <br />
                            <span className="text-shimmer italic">
                                in four steps.
                            </span>
                        </h2>

                        <div className="mt-14 flex gap-8">
                            {/* progress rail */}
                            <div
                                aria-hidden
                                className="bg-ivory/15 relative w-px"
                            >
                                <div
                                    className="bg-gold absolute top-0 left-0 w-px transition-[height] duration-150"
                                    style={{ height: `${progress * 100}%` }}
                                />
                            </div>
                            <ol className="space-y-7">
                                {steps.map((step, i) => (
                                    <li
                                        key={step.kicker}
                                        className={cn(
                                            'max-w-md transition-all duration-500',
                                            i === active
                                                ? 'opacity-100'
                                                : 'opacity-30',
                                        )}
                                    >
                                        <p className="font-display text-gold-soft text-sm italic">
                                            {step.kicker}
                                        </p>
                                        <h3 className="mt-1 text-2xl font-medium">
                                            {step.title}
                                        </h3>
                                        <div
                                            className={cn(
                                                'grid transition-all duration-500',
                                                i === active
                                                    ? 'grid-rows-[1fr]'
                                                    : 'grid-rows-[0fr]',
                                            )}
                                        >
                                            <p className="text-ivory/65 overflow-hidden pt-2 leading-relaxed">
                                                {step.body}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </div>

                    <div className="relative">
                        <PhoneMockup className="w-[300px]">
                            {steps.map((step, i) => (
                                <div
                                    key={step.kicker}
                                    className={cn(
                                        'absolute inset-0 transition-all duration-700 ease-out',
                                        i === active
                                            ? 'scale-100 opacity-100'
                                            : i < active
                                              ? 'scale-95 opacity-0'
                                              : 'scale-105 opacity-0',
                                    )}
                                >
                                    <StepScreen
                                        index={i}
                                        active={i === active}
                                    />
                                </div>
                            ))}
                        </PhoneMockup>
                    </div>
                </div>
            </div>

            {/* Mobile / tablet / reduced motion: stacked cards */}
            <div
                className={cn(
                    'mx-auto max-w-6xl px-6 py-24',
                    !reduced && 'lg:hidden',
                )}
            >
                <Reveal>
                    <p className="text-gold-soft text-sm tracking-[0.3em] uppercase">
                        How it works
                    </p>
                    <h2 className="font-display mt-4 text-4xl leading-[1.05] tracking-tight sm:text-5xl">
                        From idea to “I’ll be there”{' '}
                        <span className="text-shimmer italic">
                            in four steps.
                        </span>
                    </h2>
                </Reveal>
                <ol className="mt-14 grid gap-14 sm:grid-cols-2">
                    {steps.map((step, i) => (
                        <Reveal
                            as="li"
                            key={step.kicker}
                            delay={i * 80}
                            className="flex flex-col items-center text-center"
                        >
                            <PhoneMockup className="w-[230px] sm:w-[230px]">
                                <StepScreen index={i} active />
                            </PhoneMockup>
                            <p className="font-display text-gold-soft mt-8 text-sm italic">
                                {step.kicker}
                            </p>
                            <h3 className="mt-1 text-2xl font-medium">
                                {step.title}
                            </h3>
                            <p className="text-ivory/65 mt-2 max-w-sm leading-relaxed">
                                {step.body}
                            </p>
                        </Reveal>
                    ))}
                </ol>
            </div>
        </section>
    );
}

function StepScreen({ index, active }: { index: number; active: boolean }) {
    switch (index) {
        case 0:
            return <PickScreen />;
        case 1:
            return <CustomiseScreen active={active} />;
        case 2:
            return <WhatsAppScreen active={active} />;
        default:
            return <RsvpScreen active={active} />;
    }
}

function ScreenShell({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="bg-ivory text-ink flex h-full flex-col px-4 pt-12 pb-4">
            <p className="text-center text-[11px] font-semibold tracking-wide">
                {title}
            </p>
            <div className="mt-4 flex-1 overflow-hidden">{children}</div>
        </div>
    );
}

function PickScreen() {
    return (
        <ScreenShell title="Choose a template">
            <div className="grid grid-cols-2 gap-2.5">
                {(['classic', 'modern', 'floral', 'classic'] as const).map(
                    (variant, i) => (
                        <div
                            key={i}
                            className={cn(
                                'ring-ink/10 relative aspect-[9/14] overflow-hidden rounded-xl ring-1',
                                i === 0 && 'ring-gold ring-2',
                            )}
                        >
                            <div className="pointer-events-none absolute top-0 left-0 h-[200%] w-[200%] origin-top-left scale-50">
                                <InvitationCard
                                    variant={variant}
                                    showRsvp={false}
                                />
                            </div>
                            {i === 0 && (
                                <span className="bg-gold text-ivory absolute top-1.5 right-1.5 grid size-5 place-items-center rounded-full">
                                    <Check className="size-3" />
                                </span>
                            )}
                        </div>
                    ),
                )}
            </div>
            <div className="bg-ink text-ivory mt-3 rounded-full py-2 text-center text-[11px] font-medium">
                Use Classic
            </div>
        </ScreenShell>
    );
}

function CustomiseScreen({ active }: { active: boolean }) {
    const rows = [
        {
            icon: <ImageIcon className="size-3.5" />,
            label: 'Cover photo',
            value: 'uploaded',
        },
        {
            icon: <Video className="size-3.5" />,
            label: 'Video',
            value: 'uploaded',
        },
        {
            icon: <Music2 className="size-3.5" />,
            label: 'Background music',
            value: 'our-song.mp3',
        },
        {
            icon: <MapPin className="size-3.5" />,
            label: 'Venue',
            value: 'Cinnamon Grand, Colombo',
        },
    ];

    return (
        <ScreenShell title="Event details">
            <div className="ring-ink/5 rounded-xl bg-white p-3 shadow-sm ring-1">
                <p className="text-ink/45 text-[9px] tracking-widest uppercase">
                    Title
                </p>
                <p className="font-display text-base">
                    Nethmi &amp; Sahan’s Wedding
                </p>
                <p className="text-ink/45 mt-2 text-[9px] tracking-widest uppercase">
                    Date
                </p>
                <p className="text-xs">Sat, 14 December 2026 · 7:00 pm</p>
            </div>
            <ul className="mt-3 space-y-2">
                {rows.map((row, i) => (
                    <li
                        key={row.label}
                        className={cn(
                            'ring-ink/5 flex items-center gap-2.5 rounded-xl bg-white px-3 py-2.5 text-[11px] shadow-sm ring-1',
                            active && 'animate-slide-up',
                        )}
                        style={{ animationDelay: `${i * 120}ms` }}
                    >
                        <span className="bg-gold/15 text-gold-deep grid size-6 place-items-center rounded-lg">
                            {row.icon}
                        </span>
                        <span className="flex-1 font-medium">{row.label}</span>
                        <span className="text-ink/50 max-w-[40%] truncate">
                            {row.value}
                        </span>
                    </li>
                ))}
            </ul>
            <div
                className="mt-3 flex items-end justify-center gap-[3px]"
                aria-hidden
            >
                {[0.4, 0.8, 0.55, 1, 0.7, 0.45, 0.9, 0.6].map((h, i) => (
                    <span
                        key={i}
                        className="animate-equalizer bg-gold w-[3px] rounded-full"
                        style={{
                            height: 18 * h,
                            animationDelay: `${i * 90}ms`,
                        }}
                    />
                ))}
            </div>
        </ScreenShell>
    );
}

function WhatsAppScreen({ active }: { active: boolean }) {
    return (
        <div className="text-ink flex h-full flex-col bg-[#efe7dd]">
            <div className="flex items-center gap-2 bg-[#075e54] px-3 pt-10 pb-2.5 text-white">
                <span className="grid size-7 place-items-center rounded-full bg-white/20 text-[10px] font-semibold">
                    TP
                </span>
                <span className="text-[11px] leading-tight">
                    <strong className="block">Tharushi</strong>
                    <span className="text-white/70">online</span>
                </span>
            </div>
            <div className="flex flex-1 flex-col justify-end gap-2 p-3">
                <div
                    className={cn(
                        'ml-auto w-[88%] overflow-hidden rounded-xl rounded-tr-sm bg-[#dcf8c6] shadow-sm',
                        active && 'animate-pop-in',
                    )}
                >
                    <div className="relative h-28 overflow-hidden">
                        <div className="pointer-events-none absolute top-0 left-0 h-[250%] w-[250%] origin-top-left scale-[0.4]">
                            <InvitationCard
                                variant="classic"
                                showRsvp={false}
                            />
                        </div>
                    </div>
                    <div className="bg-[#cfeebb] px-2.5 py-1.5">
                        <p className="text-[10px] font-semibold">
                            Nethmi &amp; Sahan · 14 Dec
                        </p>
                        <p className="text-ink/60 text-[9px]">
                            You’re invited. Tap to open your invitation
                        </p>
                    </div>
                    <p className="px-2.5 pt-1.5 text-[10px]">
                        Hi Tharushi! We’d love for you to be there 💛
                    </p>
                    <p className="text-ink/45 flex items-center justify-end gap-1 px-2 pb-1 text-[9px]">
                        2:14 pm{' '}
                        <DoubleTick className="size-auto bg-transparent" />
                    </p>
                </div>
            </div>
        </div>
    );
}

function RsvpScreen({ active }: { active: boolean }) {
    const guests = [
        { name: 'Tharushi Perera', status: 'Accepted' },
        { name: 'Kasun Fernando', status: 'Accepted' },
        { name: 'Fathima Rizwan', status: 'Pending' },
        { name: 'Dilshan Jayasuriya', status: 'Accepted' },
        { name: 'Kavitha Sivakumar', status: 'Declined' },
    ];

    return (
        <ScreenShell title="Guests">
            <div className="grid grid-cols-3 gap-1.5 text-center">
                {[
                    ['124', 'Accepted'],
                    ['18', 'Pending'],
                    ['6', 'Declined'],
                ].map(([n, label]) => (
                    <div
                        key={label}
                        className="ring-ink/5 rounded-xl bg-white py-2 shadow-sm ring-1"
                    >
                        <p className="font-display text-lg leading-none">{n}</p>
                        <p className="text-ink/50 mt-1 text-[9px]">{label}</p>
                    </div>
                ))}
            </div>
            <ul className="divide-ink/5 ring-ink/5 mt-3 divide-y rounded-xl bg-white shadow-sm ring-1">
                {guests.map((guest, i) => (
                    <li
                        key={guest.name}
                        className={cn(
                            'flex items-center justify-between px-3 py-2.5 text-[11px]',
                            active && 'animate-slide-up',
                        )}
                        style={{ animationDelay: `${i * 110}ms` }}
                    >
                        <span className="font-medium">{guest.name}</span>
                        <StatusPill status={guest.status} />
                    </li>
                ))}
            </ul>
        </ScreenShell>
    );
}

export function StatusPill({ status }: { status: string }) {
    return (
        <span
            className={cn(
                'rounded-full px-2 py-0.5 text-[10px] font-medium',
                status === 'Accepted' && 'bg-emerald-50 text-emerald-700',
                status === 'Pending' && 'bg-amber-50 text-amber-700',
                status === 'Declined' && 'bg-rose-50 text-rose-700',
            )}
        >
            {status}
        </span>
    );
}
