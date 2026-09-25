import {
    Clock,
    Globe,
    ImageIcon,
    Link2,
    Play,
    ShieldCheck,
    UserCheck,
} from 'lucide-react';
import type { MouseEvent, ReactNode } from 'react';
import { useRef } from 'react';

import { cn } from '@/lib/utils';

import { useInView, useTicker } from './hooks';
import { Reveal, SplitText } from './reveal';

export function FeaturesBento() {
    return (
        <section
            id="features"
            className="relative mx-auto max-w-6xl scroll-mt-24 px-6 py-28 sm:py-36"
        >
            <div className="max-w-3xl">
                <Reveal>
                    <p className="text-gold-deep text-sm tracking-[0.3em] uppercase">
                        Features
                    </p>
                </Reveal>
                <SplitText
                    text="Everything a host needs. *Nothing* a guest has to learn."
                    className="font-display mt-4 text-[clamp(2.3rem,5vw,4rem)] leading-[1.03] font-medium tracking-tight"
                />
            </div>

            <div className="mt-16 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <Card className="lg:col-span-4" delay={0}>
                    <WhatsAppVisual />
                    <CardText
                        title="Delivered on WhatsApp"
                        body="Invitations go where your guests already are. You can see each message as sent, delivered and read, all in one place."
                    />
                </Card>
                <Card className="lg:col-span-2" delay={80}>
                    <RsvpVisual />
                    <CardText
                        title="One-tap RSVP"
                        body="Accept or Decline with one tap. No log-in, no app and no forms."
                    />
                </Card>

                <Card className="lg:col-span-2" delay={0}>
                    <NameVisual />
                    <CardText
                        title="Made for each guest"
                        body="Every invitation opens with the guest’s own name."
                    />
                </Card>
                <Card className="lg:col-span-2" delay={80}>
                    <MusicVisual />
                    <CardText
                        title="Set to music"
                        body="Add your song. It starts when the guest taps play, so it never blares unexpectedly."
                    />
                </Card>
                <Card className="lg:col-span-2" delay={160}>
                    <MediaVisual />
                    <CardText
                        title="Photos & video"
                        body="Upload engagement shots, a video or a teaser. Each design shows them in its own style."
                    />
                </Card>

                <Card className="lg:col-span-3" delay={0}>
                    <CapacityVisual />
                    <CardText
                        title="Capacity & approvals"
                        body="Set a guest limit and approve each registration yourself. EMP stops registrations when the event is full."
                    />
                </Card>
                <Card className="lg:col-span-3" delay={80}>
                    <RegistrationVisual />
                    <CardText
                        title="Public registration link"
                        body="Hosting something open? Share one link and let people register. Duplicates are caught for you."
                    />
                </Card>

                <Card className="lg:col-span-2" delay={0}>
                    <IconVisual icon={<Globe className="size-7" />} />
                    <CardText
                        title="Beautiful link previews"
                        body="Your cover photo and title show in the WhatsApp chat before anyone taps."
                    />
                </Card>
                <Card className="lg:col-span-2" delay={80}>
                    <IconVisual icon={<ShieldCheck className="size-7" />} />
                    <CardText
                        title="Private by design"
                        body="Each guest gets a unique link. Templates are checked for unsafe code before they go live."
                    />
                </Card>
                <Card className="lg:col-span-2" delay={160}>
                    <IconVisual icon={<Clock className="size-7" />} />
                    <CardText
                        title="Links that expire"
                        body="Invitation links close after 7 days by default. You can resend with one click."
                    />
                </Card>
            </div>
        </section>
    );
}

function Card({
    children,
    className,
    delay,
}: {
    children: ReactNode;
    className?: string;
    delay: number;
}) {
    const onMove = (e: MouseEvent<HTMLDivElement>) => {
        const rect = e.currentTarget.getBoundingClientRect();
        e.currentTarget.style.setProperty('--mx', `${e.clientX - rect.left}px`);
        e.currentTarget.style.setProperty('--my', `${e.clientY - rect.top}px`);
    };

    return (
        <Reveal delay={delay} className={cn('min-w-0', className)}>
            <div
                onMouseMove={onMove}
                className="spotlight border-ink/[0.07] flex h-full flex-col overflow-hidden rounded-[1.75rem] border bg-white/60 p-6 shadow-[0_1px_0_rgb(255_255_255/0.8)_inset,0_30px_60px_-45px_rgb(22_19_15/0.45)] transition-transform duration-500 hover:-translate-y-1 sm:p-7"
            >
                {children}
            </div>
        </Reveal>
    );
}

function CardText({ title, body }: { title: string; body: string }) {
    return (
        <div className="mt-auto pt-6">
            <h3 className="text-lg font-semibold tracking-tight">{title}</h3>
            <p className="text-ink/60 mt-1.5 text-[15px] leading-relaxed">
                {body}
            </p>
        </div>
    );
}

function Stage({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            aria-hidden
            className={cn(
                'bg-cream/70 relative grid min-h-40 place-items-center overflow-hidden rounded-2xl',
                className,
            )}
        >
            {children}
        </div>
    );
}

function WhatsAppVisual() {
    const ref = useRef<HTMLDivElement>(null);
    const inView = useInView(ref, { once: false });
    const step = useTicker(3, 1400, inView);
    const labels = ['Sent', 'Delivered', 'Read'];
    const guests = ['Tharushi', 'Kasun', 'Fathima'];

    return (
        <div ref={ref}>
            <Stage className="min-h-56 bg-[#efe7dd] p-6">
                <div className="flex w-full max-w-md flex-col gap-2.5">
                    {guests.map((name, i) => {
                        const s = (step + 3 - i) % 3;

                        return (
                            <div
                                key={name}
                                className="flex items-center gap-3 rounded-2xl rounded-tr-sm bg-[#dcf8c6] px-4 py-2.5 shadow-sm"
                                style={{ marginLeft: `${i * 8}%` }}
                            >
                                <span className="grid size-8 shrink-0 place-items-center rounded-full bg-[#075e54] text-[11px] font-semibold text-white">
                                    {name[0]}
                                </span>
                                <span className="min-w-0 flex-1 truncate text-sm">
                                    Invitation to{' '}
                                    <strong className="font-semibold">
                                        {name}
                                    </strong>
                                </span>
                                <span className="text-ink/50 flex shrink-0 items-center gap-1 text-xs">
                                    {labels[s]}
                                    <Ticks state={s} />
                                </span>
                            </div>
                        );
                    })}
                </div>
            </Stage>
        </div>
    );
}

function Ticks({ state }: { state: number }) {
    return (
        <svg
            viewBox="0 0 24 24"
            className={cn(
                'size-4 transition-colors duration-500',
                state === 2 ? 'text-[#34b7f1]' : 'text-ink/40',
            )}
            fill="none"
            stroke="currentColor"
            strokeWidth="2.2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="m2 13 4 4L16 7" />
            <path
                d="M11 16l1 1L22 7"
                className={cn(
                    'transition-opacity duration-500',
                    state === 0 ? 'opacity-0' : 'opacity-100',
                )}
            />
        </svg>
    );
}

function RsvpVisual() {
    const ref = useRef<HTMLDivElement>(null);
    const inView = useInView(ref, { once: false });
    const tapped = useTicker(2, 1800, inView) === 1;

    return (
        <div ref={ref}>
            <Stage>
                <div className="flex w-44 flex-col gap-2">
                    <span
                        className={cn(
                            'relative rounded-full py-2.5 text-center text-sm font-semibold transition-all duration-300',
                            tapped
                                ? 'bg-gold text-ivory scale-95'
                                : 'bg-ink text-ivory',
                        )}
                    >
                        {tapped ? '✓ Attending' : 'Accept'}
                        <span
                            className={cn(
                                'absolute top-1/2 left-1/2 size-8 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/60 transition-all duration-500',
                                tapped
                                    ? 'scale-[3] opacity-0'
                                    : 'scale-0 opacity-100',
                            )}
                        />
                    </span>
                    <span className="border-ink/15 text-ink/60 rounded-full border py-2.5 text-center text-sm">
                        Decline
                    </span>
                </div>
                {/* finger */}
                <span
                    className={cn(
                        'border-ink/30 absolute size-9 rounded-full border-2 bg-white/50 backdrop-blur transition-all duration-500',
                        tapped
                            ? 'top-[34%] left-[56%] scale-90'
                            : 'top-[62%] left-[70%] scale-100',
                    )}
                />
            </Stage>
        </div>
    );
}

function NameVisual() {
    const ref = useRef<HTMLDivElement>(null);
    const inView = useInView(ref, { once: false });
    const names = ['Tharushi', 'Kasun', 'Fathima', 'Dilshan'];
    const index = useTicker(names.length, 2200, inView);

    return (
        <div ref={ref}>
            <Stage>
                <p className="font-display text-3xl">
                    Dear{' '}
                    <span
                        key={index}
                        className="animate-slide-up text-gold-deep inline-block italic"
                    >
                        {names[index]}
                    </span>
                    <span className="animate-caret bg-gold ml-0.5 inline-block h-7 w-[2px] translate-y-1" />
                </p>
            </Stage>
        </div>
    );
}

function MusicVisual() {
    return (
        <Stage>
            <div className="flex items-center gap-4">
                <span className="bg-ink text-ivory grid size-12 place-items-center rounded-full shadow-lg">
                    <Play className="ml-0.5 size-5 fill-current" />
                </span>
                <span className="flex h-10 items-end gap-1">
                    {[0.5, 0.9, 0.35, 0.75, 1, 0.6, 0.85, 0.4, 0.7, 0.55].map(
                        (h, i) => (
                            <span
                                key={i}
                                className="animate-equalizer bg-gold w-1.5 rounded-full"
                                style={{
                                    height: `${h * 100}%`,
                                    animationDelay: `${i * 110}ms`,
                                }}
                            />
                        ),
                    )}
                </span>
            </div>
        </Stage>
    );
}

function MediaVisual() {
    return (
        <Stage className="[perspective:600px]">
            <div className="group/media relative h-24 w-36">
                {[
                    'from-[#e8c9a0] to-[#b08d57] -rotate-12 -translate-x-6',
                    'from-[#f4d6d6] to-[#d69aa2] rotate-6 translate-x-6',
                    'from-[#2a2520] to-[#4a4038] rotate-0',
                ].map((cls, i) => (
                    <div
                        key={i}
                        className={cn(
                            'absolute inset-0 grid place-items-center rounded-xl bg-gradient-to-br shadow-lg ring-4 ring-white transition-transform duration-500 group-hover/media:scale-105',
                            cls,
                        )}
                    >
                        {i === 2 && (
                            <Play className="fill-ivory text-ivory size-6" />
                        )}
                        {i === 1 && (
                            <ImageIcon className="size-6 text-white/80" />
                        )}
                    </div>
                ))}
            </div>
        </Stage>
    );
}

function CapacityVisual() {
    const ref = useRef<HTMLDivElement>(null);
    const inView = useInView(ref);

    return (
        <div ref={ref}>
            <Stage className="p-6">
                <div className="w-full max-w-sm space-y-4">
                    <div>
                        <div className="flex justify-between text-sm">
                            <span className="font-medium">Seats filled</span>
                            <span className="text-ink/55">184 / 200</span>
                        </div>
                        <div className="bg-ink/10 mt-2 h-2.5 overflow-hidden rounded-full">
                            <div
                                className="from-gold-soft to-gold-deep h-full rounded-full bg-gradient-to-r transition-[width] duration-[1600ms] ease-out"
                                style={{ width: inView ? '92%' : '0%' }}
                            />
                        </div>
                    </div>
                    <div className="flex items-center gap-3 rounded-xl bg-white px-4 py-3 text-sm shadow-sm">
                        <UserCheck className="text-gold-deep size-4" />
                        <span className="flex-1">Ravindu wants to join</span>
                        <span className="bg-ink text-ivory rounded-full px-3 py-1 text-xs">
                            Approve
                        </span>
                    </div>
                </div>
            </Stage>
        </div>
    );
}

function RegistrationVisual() {
    return (
        <Stage className="p-6">
            <div className="w-full max-w-sm rounded-2xl bg-white p-4 shadow-sm">
                <div className="bg-cream text-ink/60 flex items-center gap-2 rounded-lg px-3 py-2 text-xs">
                    <Link2 className="size-3.5" />
                    <span className="truncate">
                        emp.app/e/avurudu-night-2027
                    </span>
                    <span className="bg-ink text-ivory ml-auto rounded-md px-2 py-0.5 text-[10px]">
                        Copy
                    </span>
                </div>
                <div className="mt-3 space-y-2">
                    <div className="border-ink/10 text-ink/40 h-8 rounded-lg border px-3 text-xs leading-8">
                        Your name
                    </div>
                    <div className="border-ink/10 text-ink/40 h-8 rounded-lg border px-3 text-xs leading-8">
                        WhatsApp number
                    </div>
                    <div className="bg-gold text-ivory h-8 rounded-lg text-center text-xs leading-8 font-medium">
                        Register
                    </div>
                </div>
            </div>
        </Stage>
    );
}

function IconVisual({ icon }: { icon: ReactNode }) {
    return (
        <div
            aria-hidden
            className="from-gold-soft/60 to-gold/30 text-gold-deep ring-gold/20 grid size-14 place-items-center rounded-2xl bg-gradient-to-br ring-1"
        >
            {icon}
        </div>
    );
}
