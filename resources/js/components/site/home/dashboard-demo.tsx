import { useEffect, useRef, useState } from 'react';

import { cn } from '@/lib/utils';

import { useCountUp, useInView } from './hooks';
import { Reveal, SplitText } from './reveal';
import { StatusPill } from './story';

type Status = 'Accepted' | 'Declined' | 'Pending';

const guests: { name: string; party: string; final: Status }[] = [
    { name: 'Tharushi Perera', party: 'Bride’s friends', final: 'Accepted' },
    { name: 'Kasun Fernando', party: 'Groom’s family', final: 'Accepted' },
    { name: 'Fathima Rizwan', party: 'School batch', final: 'Pending' },
    { name: 'Dilshan Jayasuriya', party: 'Groom’s family', final: 'Accepted' },
    { name: 'Kavitha Sivakumar', party: 'Work', final: 'Declined' },
    { name: 'Ravindu Silva', party: 'Bride’s family', final: 'Accepted' },
];

const totals = { accepted: 142, pending: 31, declined: 9 };

/** A mock client dashboard: guest rows flip to their RSVP one by one. */
export function DashboardDemo() {
    const ref = useRef<HTMLDivElement>(null);
    const inView = useInView(ref, { threshold: 0.35 });
    const [resolved, setResolved] = useState(0);

    useEffect(() => {
        if (!inView) {
            return;
        }
        const id = window.setInterval(
            () => setResolved((n) => Math.min(guests.length, n + 1)),
            550,
        );

        return () => window.clearInterval(id);
    }, [inView]);

    const accepted = useCountUp(totals.accepted, inView);
    const pending = useCountUp(totals.pending, inView);
    const declined = useCountUp(totals.declined, inView);
    const all = totals.accepted + totals.pending + totals.declined;

    return (
        <section className="bg-cream/60 relative overflow-hidden py-28 sm:py-36">
            <div className="mx-auto grid max-w-6xl items-center gap-16 px-6 lg:grid-cols-[1fr_1.2fr]">
                <div>
                    <Reveal>
                        <p className="text-gold-deep text-sm tracking-[0.3em] uppercase">
                            Your dashboard
                        </p>
                    </Reveal>
                    <SplitText
                        text="Know who’s coming, *as it happens.*"
                        className="font-display mt-4 text-[clamp(2.3rem,5vw,4rem)] leading-[1.03] font-medium tracking-tight"
                    />
                    <Reveal delay={200}>
                        <p className="text-ink/60 mt-6 max-w-md text-lg leading-relaxed">
                            Every tap on Accept or Decline shows up on your
                            guest list straight away. Plan the catering, the
                            seating and the transport from real numbers, not
                            guesses.
                        </p>
                    </Reveal>

                    <Reveal delay={300}>
                        <div className="mt-10 flex items-center gap-8">
                            <Donut
                                values={[
                                    totals.accepted,
                                    totals.pending,
                                    totals.declined,
                                ]}
                                animate={inView}
                            />
                            <div className="text-sm">
                                <dl className="space-y-3">
                                    <Legend
                                        color="bg-gold"
                                        label="Accepted"
                                        value={accepted}
                                    />
                                    <Legend
                                        color="bg-gold-soft"
                                        label="Pending"
                                        value={pending}
                                    />
                                    <Legend
                                        color="bg-ink/70"
                                        label="Declined"
                                        value={declined}
                                    />
                                </dl>
                                <p className="text-ink/45 pt-4">
                                    {all} invited
                                </p>
                            </div>
                        </div>
                    </Reveal>
                </div>

                <Reveal delay={150}>
                    <div
                        ref={ref}
                        className="border-ink/10 overflow-hidden rounded-[1.75rem] border bg-white shadow-[0_60px_120px_-60px_rgb(22_19_15/0.5)]"
                    >
                        <div className="border-ink/5 flex items-center gap-2 border-b px-5 py-3.5">
                            <span className="size-3 rounded-full bg-[#ff5f57]" />
                            <span className="size-3 rounded-full bg-[#febc2e]" />
                            <span className="size-3 rounded-full bg-[#28c840]" />
                            <span className="text-ink/45 ml-4 truncate text-xs">
                                Nethmi &amp; Sahan’s Wedding · Guests
                            </span>
                        </div>
                        <div className="text-ink/40 grid grid-cols-[1fr_auto] gap-x-4 px-5 pt-4 pb-2 text-[11px] tracking-widest uppercase sm:grid-cols-[1fr_1fr_auto]">
                            <span>Guest</span>
                            <span className="hidden sm:block">Group</span>
                            <span>RSVP</span>
                        </div>
                        <ul className="divide-ink/5 divide-y">
                            {guests.map((guest, i) => {
                                const status: Status =
                                    i < resolved ? guest.final : 'Pending';

                                return (
                                    <li
                                        key={guest.name}
                                        className="grid grid-cols-[1fr_auto] items-center gap-x-4 px-5 py-3.5 text-sm sm:grid-cols-[1fr_1fr_auto]"
                                    >
                                        <span className="flex items-center gap-3">
                                            <span className="bg-cream text-gold-deep grid size-8 place-items-center rounded-full text-xs font-semibold">
                                                {guest.name
                                                    .split(' ')
                                                    .map((p) => p[0])
                                                    .join('')}
                                            </span>
                                            <span className="font-medium">
                                                {guest.name}
                                            </span>
                                        </span>
                                        <span className="text-ink/50 hidden sm:block">
                                            {guest.party}
                                        </span>
                                        <span
                                            key={status}
                                            className={cn(
                                                status !== 'Pending' &&
                                                    'animate-pop-in',
                                            )}
                                        >
                                            <StatusPill status={status} />
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </Reveal>
            </div>
        </section>
    );
}

function Legend({
    color,
    label,
    value,
}: {
    color: string;
    label: string;
    value: number;
}) {
    return (
        <div className="flex items-center gap-2.5">
            <span className={cn('size-2.5 rounded-full', color)} />
            <dt className="text-ink/60 w-20">{label}</dt>
            <dd className="font-display text-xl tabular-nums">{value}</dd>
        </div>
    );
}

function Donut({ values, animate }: { values: number[]; animate: boolean }) {
    const total = values.reduce((a, b) => a + b, 0);
    const r = 52;
    const c = 2 * Math.PI * r;
    const colors = [
        'var(--color-gold)',
        'var(--color-gold-soft)',
        'rgb(22 19 15 / 0.7)',
    ];
    let offset = 0;

    return (
        <svg viewBox="0 0 140 140" className="size-36 -rotate-90" aria-hidden>
            <circle
                cx="70"
                cy="70"
                r={r}
                fill="none"
                stroke="rgb(22 19 15 / 0.06)"
                strokeWidth="14"
            />
            {values.map((v, i) => {
                const len = (v / total) * c;
                const el = (
                    <circle
                        key={i}
                        cx="70"
                        cy="70"
                        r={r}
                        fill="none"
                        stroke={colors[i]}
                        strokeWidth="14"
                        strokeDasharray={`${animate ? Math.max(0, len - 3) : 0} ${c}`}
                        strokeDashoffset={-offset}
                        strokeLinecap="round"
                        style={{
                            transition: `stroke-dasharray 1.4s cubic-bezier(.2,.7,.2,1) ${i * 200}ms`,
                        }}
                    />
                );
                offset += len;

                return el;
            })}
        </svg>
    );
}
