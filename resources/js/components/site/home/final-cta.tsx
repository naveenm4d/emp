import { ArrowRight } from 'lucide-react';
import type { CSSProperties } from 'react';
import { useState } from 'react';

import { SplitText, Reveal } from './reveal';

const confettiColors = ['#b08d57', '#d9c29a', '#f1dfb8', '#f7f3ec', '#e8a7b0'];

export function FinalCta({ dashboardUrl }: { dashboardUrl: string }) {
    const [burst, setBurst] = useState(0);

    return (
        <section className="px-3 pb-3 sm:px-6 sm:pb-6">
            <div className="grain bg-ink text-ivory relative isolate overflow-hidden rounded-[2rem] px-6 py-24 text-center sm:rounded-[2.5rem] sm:py-32">
                <div
                    aria-hidden
                    className="absolute -bottom-1/2 left-1/2 -z-10 size-[900px] -translate-x-1/2 rounded-full bg-[radial-gradient(circle,rgb(176_141_87/0.45),transparent_62%)]"
                />
                <div
                    aria-hidden
                    className="animate-spin-slow border-gold/20 absolute top-1/2 left-1/2 -z-10 size-[620px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-dashed"
                />

                <SplitText
                    text="Your guests are *waiting.*"
                    className="font-display mx-auto max-w-4xl text-[clamp(2.8rem,8vw,6.5rem)] leading-[0.98] font-medium tracking-[-0.03em]"
                />
                <Reveal delay={250}>
                    <p className="text-ivory/60 mx-auto mt-6 max-w-lg text-lg">
                        Make your first invitation free, send it in minutes and
                        watch the RSVPs arrive.
                    </p>
                </Reveal>
                <Reveal delay={400}>
                    <div className="relative mt-10 inline-block">
                        <a
                            href={dashboardUrl}
                            onMouseEnter={() => setBurst((b) => b + 1)}
                            onFocus={() => setBurst((b) => b + 1)}
                            className="group bg-gold text-ink hover:bg-gold-soft relative z-10 inline-flex items-center gap-2 rounded-full px-8 py-4 text-base font-medium shadow-[0_20px_60px_-15px_rgb(176_141_87/0.8)] transition-all hover:-translate-y-0.5"
                        >
                            Get started, it’s free
                            <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                        </a>
                        {burst > 0 && <Confetti key={burst} />}
                    </div>
                </Reveal>
            </div>
        </section>
    );
}

function Confetti() {
    const pieces = Array.from({ length: 22 }, (_, i) => {
        const angle = (i / 22) * Math.PI * 2;
        const distance = 90 + (i % 5) * 22;

        return {
            dx: `${Math.cos(angle) * distance}px`,
            dy: `${Math.sin(angle) * distance - 30}px`,
            rot: `${(i % 2 ? 1 : -1) * (180 + i * 20)}deg`,
            color: confettiColors[i % confettiColors.length],
            round: i % 3 === 0,
        };
    });

    return (
        <span
            aria-hidden
            className="pointer-events-none absolute top-1/2 left-1/2"
        >
            {pieces.map((p, i) => (
                <span
                    key={i}
                    className="absolute block"
                    style={
                        {
                            width: p.round ? 7 : 5,
                            height: p.round ? 7 : 10,
                            borderRadius: p.round ? 9999 : 2,
                            background: p.color,
                            '--dx': p.dx,
                            '--dy': p.dy,
                            '--rot': p.rot,
                            animation:
                                'confetti 0.9s cubic-bezier(.15,.7,.3,1) forwards',
                        } as CSSProperties
                    }
                />
            ))}
        </span>
    );
}
