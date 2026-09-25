import { cn } from '@/lib/utils';

import type { TemplateVariant } from './content';

/**
 * Miniature invitations drawn in CSS/SVG. They echo the starter templates
 * (classic, modern, floral) without loading any remote images.
 */
export function InvitationCard({
    variant,
    guest = 'Tharushi',
    className,
    showRsvp = true,
}: {
    variant: TemplateVariant;
    guest?: string;
    className?: string;
    showRsvp?: boolean;
}) {
    if (variant === 'modern') {
        return (
            <div
                className={cn(
                    'relative flex h-full w-full flex-col overflow-hidden bg-[#12161f] px-6 pt-12 pb-6 text-[#efe6d4]',
                    className,
                )}
            >
                <div
                    aria-hidden
                    className="absolute -top-16 -right-16 size-48 rounded-full bg-[radial-gradient(circle,#c9a36a_0%,transparent_65%)] opacity-40"
                />
                <div
                    aria-hidden
                    className="absolute bottom-24 -left-20 size-56 rounded-full border border-[#c9a36a]/30"
                />
                <p className="text-[10px] tracking-[0.35em] text-[#c9a36a] uppercase">
                    You're invited
                </p>
                <p className="font-display mt-6 text-[40px] leading-[0.9] font-medium">
                    Nethmi
                    <br />
                    <span className="text-[#c9a36a] italic">&amp;</span> Sahan
                </p>
                <div className="mt-5 h-px w-12 bg-[#c9a36a]" />
                <p className="mt-4 text-[11px] leading-relaxed text-[#efe6d4]/70">
                    Dear {guest}, join us as we
                    <br />
                    begin forever.
                </p>
                <div className="mt-auto grid grid-cols-3 border-y border-[#efe6d4]/15 py-3 text-center text-[10px] tracking-widest uppercase">
                    <span>Sat</span>
                    <span className="font-display border-x border-[#efe6d4]/15 text-base leading-none tracking-normal">
                        14
                    </span>
                    <span>Dec</span>
                </div>
                {showRsvp && <RsvpButtons tone="dark" />}
            </div>
        );
    }

    if (variant === 'floral') {
        return (
            <div
                className={cn(
                    'relative flex h-full w-full flex-col items-center overflow-hidden bg-[#fbeeee] px-6 pt-12 pb-6 text-center text-[#5b3b40]',
                    className,
                )}
            >
                <Petals className="absolute -top-6 -left-8 w-40 text-[#e8a7b0]" />
                <Petals className="absolute -right-10 -bottom-4 w-44 rotate-180 text-[#b9c9a7]" />
                <p className="relative text-[10px] tracking-[0.3em] text-[#b07a83] uppercase">
                    Together with their families
                </p>
                <p className="font-display relative mt-5 text-[34px] leading-none italic">
                    Keerthana
                </p>
                <p className="font-display relative my-1 text-sm text-[#b07a83]">
                    &amp;
                </p>
                <p className="font-display relative text-[34px] leading-none italic">
                    Arun
                </p>
                <p className="relative mt-5 text-[11px] leading-relaxed text-[#5b3b40]/75">
                    Dear {guest}, you are warmly
                    <br />
                    invited to our engagement.
                </p>
                <p className="relative mt-4 text-[10px] tracking-[0.25em] uppercase">
                    02 · 02 · 2027 · Jaffna
                </p>
                {showRsvp && <RsvpButtons tone="rose" />}
            </div>
        );
    }

    return (
        <div
            className={cn(
                'text-ink relative flex h-full w-full flex-col items-center overflow-hidden bg-[#fbf7ef] px-6 pt-12 pb-6 text-center',
                className,
            )}
        >
            <div
                aria-hidden
                className="border-gold/50 absolute inset-3 rounded-[1.6rem] border"
            />
            <div
                aria-hidden
                className="border-gold/25 absolute inset-[18px] rounded-[1.3rem] border"
            />
            <svg
                aria-hidden
                viewBox="0 0 40 40"
                className="text-gold relative mt-2 size-8"
            >
                <path
                    d="M20 4c3 6 9 8 16 8-7 0-13 2-16 8-3-6-9-8-16-8 7 0 13-2 16-8Zm0 16c3 6 9 8 16 8-7 0-13 2-16 8-3-6-9-8-16-8 7 0 13-2 16-8Z"
                    fill="currentColor"
                    opacity=".8"
                />
            </svg>
            <p className="text-gold-deep relative mt-4 text-[10px] tracking-[0.35em] uppercase">
                Save the date
            </p>
            <p className="font-display relative mt-4 text-[26px] leading-tight whitespace-nowrap">
                Nethmi <span className="text-gold italic">&amp;</span> Sahan
            </p>
            <p className="text-ink/65 relative mt-3 text-[11px] leading-relaxed">
                Dear {guest}, with joy we invite
                <br />
                you to celebrate our wedding.
            </p>
            <p className="font-display relative mt-4 text-lg">
                14 December 2026
            </p>
            <p className="text-ink/60 relative text-[9px] tracking-[0.2em] uppercase">
                Cinnamon Grand, Colombo
            </p>
            {showRsvp && <RsvpButtons tone="light" />}
        </div>
    );
}

function RsvpButtons({ tone }: { tone: 'light' | 'dark' | 'rose' }) {
    const accept = {
        light: 'bg-ink text-ivory',
        dark: 'bg-[#c9a36a] text-[#12161f]',
        rose: 'bg-[#5b3b40] text-[#fbeeee]',
    }[tone];
    const decline = {
        light: 'border-ink/20 text-ink/70',
        dark: 'border-[#efe6d4]/25 text-[#efe6d4]/80',
        rose: 'border-[#5b3b40]/25 text-[#5b3b40]/80',
    }[tone];

    return (
        <div className="relative mt-auto flex w-full gap-2 pt-4">
            <span
                className={cn(
                    'animate-pulse-ring flex-1 rounded-full py-2 text-center text-[11px] font-semibold',
                    accept,
                )}
            >
                Accept
            </span>
            <span
                className={cn(
                    'flex-1 rounded-full border py-2 text-center text-[11px] font-medium',
                    decline,
                )}
            >
                Decline
            </span>
        </div>
    );
}

function Petals({ className }: { className?: string }) {
    return (
        <svg
            aria-hidden
            viewBox="0 0 120 120"
            className={cn('opacity-70', className)}
        >
            <g fill="currentColor">
                <ellipse
                    cx="40"
                    cy="40"
                    rx="30"
                    ry="12"
                    transform="rotate(35 40 40)"
                />
                <ellipse
                    cx="60"
                    cy="30"
                    rx="24"
                    ry="9"
                    transform="rotate(-20 60 30)"
                    opacity=".7"
                />
                <ellipse
                    cx="30"
                    cy="66"
                    rx="22"
                    ry="8"
                    transform="rotate(70 30 66)"
                    opacity=".6"
                />
                <circle cx="44" cy="44" r="6" opacity=".9" />
                <ellipse
                    cx="85"
                    cy="55"
                    rx="16"
                    ry="6"
                    transform="rotate(10 85 55)"
                    opacity=".5"
                />
            </g>
        </svg>
    );
}
