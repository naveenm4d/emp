import { useRef } from 'react';

import { facts } from './content';
import { useCountUp, useInView } from './hooks';

export function FactsBand() {
    const ref = useRef<HTMLDListElement>(null);
    const inView = useInView(ref, { threshold: 0.4 });

    return (
        <section
            aria-label="EMP at a glance"
            className="border-ink/10 border-y"
        >
            <dl
                ref={ref}
                className="divide-ink/10 mx-auto grid max-w-6xl grid-cols-2 px-6 lg:grid-cols-4 lg:divide-x"
            >
                {facts.map((fact) => (
                    <Fact key={fact.label} {...fact} start={inView} />
                ))}
            </dl>
        </section>
    );
}

function Fact({
    value,
    suffix,
    label,
    start,
}: {
    value: number;
    suffix: string;
    label: string;
    start: boolean;
}) {
    const n = useCountUp(value, start, 1200);

    return (
        <div className="flex flex-col-reverse items-center py-12 text-center">
            <dt className="text-ink/55 mt-2 text-sm">{label}</dt>
            <dd className="font-display text-6xl font-medium tracking-tight tabular-nums sm:text-7xl">
                {n}
                <span className="text-gold">{suffix}</span>
            </dd>
        </div>
    );
}
