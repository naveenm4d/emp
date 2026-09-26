import { Plus } from 'lucide-react';

import { faqs } from './content';
import { Reveal, SplitText } from './reveal';

export function Faq() {
    return (
        <section
            id="faq"
            className="mx-auto grid max-w-6xl scroll-mt-24 gap-12 px-6 py-28 sm:py-36 lg:grid-cols-[1fr_1.4fr]"
        >
            <div>
                <Reveal>
                    <p className="text-gold-deep text-sm tracking-[0.3em] uppercase">
                        FAQ
                    </p>
                </Reveal>
                <SplitText
                    text="Questions, *answered.*"
                    className="font-display mt-4 text-[clamp(2.3rem,5vw,4rem)] leading-[1.03] font-medium tracking-tight"
                />
                <Reveal delay={200}>
                    <p className="text-ink/60 mt-5 max-w-sm text-lg">
                        Everything you need to know before you send your first
                        invitation.
                    </p>
                </Reveal>
            </div>

            <div className="divide-ink/10 border-ink/10 divide-y border-y">
                {faqs.map((item, i) => (
                    <Reveal key={item.q} delay={i * 60}>
                        <details className="group py-2">
                            <summary className="flex cursor-pointer items-center justify-between gap-6 py-4 text-left text-lg font-medium">
                                {item.q}
                                <span className="border-ink/15 group-open:border-gold group-open:bg-gold group-open:text-ivory grid size-9 shrink-0 place-items-center rounded-full border transition-all duration-300 group-open:rotate-45">
                                    <Plus className="size-4" />
                                </span>
                            </summary>
                            <div className="faq-body">
                                <p className="text-ink/60 overflow-hidden pr-14 leading-relaxed">
                                    <span className="block pb-5">{item.a}</span>
                                </p>
                            </div>
                        </details>
                    </Reveal>
                ))}
            </div>
        </section>
    );
}
