import { Sparkles } from 'lucide-react';
import type { MouseEvent, ReactNode } from 'react';

import { templates } from './content';
import { InvitationCard } from './invitation-card';
import { Reveal, SplitText } from './reveal';

export function TemplatesShowcase() {
    return (
        <section id="templates" className="scroll-mt-24 py-28 sm:py-36">
            <div className="mx-auto flex max-w-6xl flex-col gap-6 px-6 md:flex-row md:items-end md:justify-between">
                <div className="max-w-2xl">
                    <Reveal>
                        <p className="text-gold-deep text-sm tracking-[0.3em] uppercase">
                            Templates
                        </p>
                    </Reveal>
                    <SplitText
                        text="Designed like *stationery.* Delivered like a text."
                        className="font-display mt-4 text-[clamp(2.3rem,5vw,4rem)] leading-[1.03] font-medium tracking-tight"
                    />
                </div>
                <Reveal delay={200}>
                    <p className="text-ink/60 max-w-sm text-lg leading-relaxed">
                        Each template is made by a designer. You add your
                        photos, words and music, and it handles the rest.
                    </p>
                </Reveal>
            </div>

            <div className="no-scrollbar mt-16 flex snap-x snap-mandatory gap-6 overflow-x-auto px-6 pb-10 [perspective:1400px] lg:justify-center">
                {templates.map((t, i) => (
                    <Reveal
                        key={t.variant}
                        delay={i * 120}
                        className="shrink-0 snap-center"
                    >
                        <TiltCard>
                            <div className="ring-ink/10 aspect-[9/16] w-[260px] overflow-hidden rounded-[1.75rem] shadow-[0_50px_80px_-40px_rgb(22_19_15/0.55)] ring-1 sm:w-[280px]">
                                <InvitationCard variant={t.variant} />
                            </div>
                        </TiltCard>
                        <p className="font-display mt-6 text-2xl">{t.name}</p>
                        <p className="text-ink/55 text-sm">{t.mood}</p>
                    </Reveal>
                ))}
                <Reveal
                    delay={templates.length * 120}
                    className="shrink-0 snap-center"
                >
                    <div className="border-gold/50 from-cream to-ivory grid aspect-[9/16] w-[260px] place-items-center rounded-[1.75rem] border border-dashed bg-gradient-to-b p-8 text-center sm:w-[280px]">
                        <div>
                            <Sparkles className="text-gold mx-auto size-8" />
                            <p className="font-display mt-4 text-2xl leading-tight">
                                More arriving every season
                            </p>
                            <p className="text-ink/55 mt-2 text-sm">
                                Homecomings, birthdays, Avurudu parties,
                                almsgivings and more.
                            </p>
                        </div>
                    </div>
                    <p className="font-display mt-6 text-2xl">Coming soon</p>
                    <p className="text-ink/55 text-sm">
                        New designs every season
                    </p>
                </Reveal>
            </div>
        </section>
    );
}

function TiltCard({ children }: { children: ReactNode }) {
    const onMove = (e: MouseEvent<HTMLDivElement>) => {
        const rect = e.currentTarget.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width - 0.5;
        const y = (e.clientY - rect.top) / rect.height - 0.5;
        e.currentTarget.style.transform = `rotateY(${x * 14}deg) rotateX(${-y * 14}deg) translateZ(20px)`;
    };
    const onLeave = (e: MouseEvent<HTMLDivElement>) => {
        e.currentTarget.style.transform = '';
    };

    return (
        <div
            onMouseMove={onMove}
            onMouseLeave={onLeave}
            className="transition-transform duration-300 ease-out [transform-style:preserve-3d]"
        >
            {children}
        </div>
    );
}
