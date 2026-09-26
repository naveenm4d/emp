import { occasions } from './content';

export function OccasionsMarquee() {
    const row = [...occasions, ...occasions];

    return (
        <section
            aria-label="Occasions"
            className="border-ink/10 bg-cream/60 border-y py-6"
        >
            <p className="sr-only">EMP works for {occasions.join(', ')}.</p>
            <div aria-hidden className="marquee-mask overflow-hidden">
                <div className="animate-marquee flex w-max items-center gap-10 hover:[animation-play-state:paused]">
                    {row.map((word, i) => (
                        <span
                            key={i}
                            className="font-display text-ink/80 flex items-center gap-10 text-3xl whitespace-nowrap italic sm:text-4xl"
                        >
                            {word}
                            <svg
                                viewBox="0 0 20 20"
                                className="text-gold size-4"
                            >
                                <path
                                    d="M10 0c1 5.5 4.5 9 10 10-5.5 1-9 4.5-10 10-1-5.5-4.5-9-10-10C5.5 9 9 5.5 10 0Z"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>
                    ))}
                </div>
            </div>
        </section>
    );
}
