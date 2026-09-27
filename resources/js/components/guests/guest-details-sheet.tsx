import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    Check,
    ChevronDown,
    CircleDashed,
    Eye,
    HelpCircle,
    Mail,
    MessageCircle,
    MessageSquareText,
    Phone,
    Send,
    Sofa,
    StickyNote,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { GuestQuickActions } from '@/components/guests/guest-actions';
import { GuestTimeline } from '@/components/guests/guest-timeline';
import type {
    GuestAnswer,
    GuestDetailsData,
} from '@/components/guests/guest-timeline';
import {
    Sheet,
    SheetBody,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatShortDate, initials, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Guest, MessageLimits, RegistrationType } from '@/types';

const sourceLabels: Record<Guest['source'], string> = {
    manual: 'Added by you',
    public_link: 'Registered on the public page',
    import: 'Imported',
    api: 'Added via API',
};

type GuestDetailsSheetProps = {
    /** The selected guest (from the refreshed list, so statuses stay live); null closes the sheet. */
    guest: Guest | null;
    /** The page's `guestDetails` prop (history and answers), loaded for `?guest=`. */
    details: GuestDetailsData | null | undefined;
    onClose: () => void;
    registrationType: RegistrationType;
    /** The event's per-guest message limits. */
    messageLimits?: MessageLimits;
    /** Show the approval status (see showsApproval). */
    showsApproval: boolean;
    onEdit: (guest: Guest) => void;
};

/** How the guest's reply reads at the top of the panel. */
const replies: Record<
    Guest['rsvp_status'],
    { label: string; icon: LucideIcon; tone: string; ring: string }
> = {
    confirmed: {
        label: 'Attending',
        icon: Check,
        tone: 'bg-success-muted text-success',
        ring: 'ring-success',
    },
    declined: {
        label: 'Can’t make it',
        icon: X,
        tone: 'bg-destructive-muted text-destructive',
        ring: 'ring-destructive',
    },
    maybe: {
        label: 'Not sure yet',
        icon: HelpCircle,
        tone: 'bg-warning-muted text-warning',
        ring: 'ring-warning',
    },
    pending: {
        label: 'Waiting for a reply',
        icon: CircleDashed,
        tone: 'bg-foreground/6 text-muted-foreground',
        ring: 'ring-foreground/15',
    },
    not_sent: {
        label: 'Not invited yet',
        icon: CircleDashed,
        tone: 'bg-foreground/6 text-muted-foreground',
        ring: 'ring-foreground/15',
    },
};

/**
 * One guest at a glance: who they are and where their reply stands, every
 * action as an icon, how the invitation travelled, their party and table,
 * what they answered, and the full activity underneath.
 */
export function GuestDetailsSheet({
    guest,
    details,
    onClose,
    registrationType,
    messageLimits,
    showsApproval,
    onEdit,
}: GuestDetailsSheetProps) {
    // Keep the last guest while the sheet animates closed.
    const [shown, setShown] = useState(guest);

    useEffect(() => {
        if (guest) {
            setShown(guest);
        }
    }, [guest]);

    // Ignore details still held for a previously opened guest.
    const shownDetails =
        details && details.guest_id === shown?.id ? details : null;
    const answers = shownDetails?.answers ?? [];

    return (
        <Sheet
            open={guest !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <SheetContent className="bg-background">
                {shown && (
                    <SheetBody className="grid grid-cols-1 content-start gap-6 p-0 pb-8 *:min-w-0">
                        <Hero guest={shown} approval={showsApproval} />

                        <div className="px-4">
                            <GuestQuickActions
                                key={shown.id}
                                guest={shown}
                                registrationType={registrationType}
                                messageLimits={messageLimits}
                                onEdit={() => onEdit(shown)}
                            />
                        </div>

                        <div className="grid grid-cols-1 gap-6 px-4 *:min-w-0">
                            {(shown.phone || shown.email) && (
                                <Block title="Contact">
                                    <Contact guest={shown} />
                                </Block>
                            )}

                            {shown.latest_rsvp && <Journey guest={shown} />}

                            <Glance guest={shown} limits={messageLimits} />

                            {hasAnswers(shown, answers) && (
                                <Block title="RSVP answers">
                                    <Answers guest={shown} answers={answers} />
                                </Block>
                            )}

                            {(shown.notes ||
                                shown.invitation_message ||
                                shown.reminder_message) && (
                                <Block title="Your notes">
                                    <Notes guest={shown} />
                                </Block>
                            )}

                            <Activity guest={shown} details={shownDetails} />

                            <p className="text-center text-xs text-subtle">
                                {sourceLabels[shown.source]} ·{' '}
                                {formatShortDate(shown.created_at)}
                            </p>
                        </div>
                    </SheetBody>
                )}
            </SheetContent>
        </Sheet>
    );
}

/** Avatar ringed in the reply's colour, name, contact and the reply itself. */
function Hero({ guest, approval }: { guest: Guest; approval: boolean }) {
    const reply = replies[guest.rsvp_status];
    const rsvp = guest.latest_rsvp;
    const when =
        guest.rsvp_status === 'pending'
            ? rsvp?.sent_at && `invited ${timeAgo(rsvp.sent_at)}`
            : ['confirmed', 'declined', 'maybe'].includes(guest.rsvp_status)
              ? rsvp?.responded_at && `replied ${timeAgo(rsvp.responded_at)}`
              : null;

    return (
        <div className="flex flex-col items-center gap-3 bg-linear-to-b from-card to-background px-4 pt-8 pb-1 text-center">
            <span
                className={cn(
                    'flex size-18 items-center justify-center rounded-full bg-strong text-2xl font-bold text-strong-foreground ring-4 ring-offset-4 ring-offset-card',
                    reply.ring,
                )}
            >
                {initials(guest.name)}
            </span>
            <div className="max-w-full min-w-0">
                <SheetTitle className="truncate text-xl font-bold">
                    {guest.name}
                </SheetTitle>
                <SheetDescription className="mt-0.5 truncate text-sm">
                    {sourceLabels[guest.source]}
                </SheetDescription>
            </div>
            <div className="flex flex-wrap justify-center gap-1.5">
                <span
                    className={cn(
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold',
                        reply.tone,
                    )}
                >
                    <reply.icon className="size-3.5" strokeWidth={2.25} />
                    {reply.label}
                    {when && (
                        <span className="font-normal opacity-80">· {when}</span>
                    )}
                </span>
                {approval && guest.approval_status !== 'approved' && (
                    <span className="inline-flex items-center rounded-full bg-warning-muted px-3 py-1 text-xs font-semibold text-warning capitalize">
                        {guest.approval_status === 'pending'
                            ? 'Waiting for approval'
                            : guest.approval_status}
                    </span>
                )}
            </div>
        </div>
    );
}

/** The invitation's way to the guest: sent, delivered, opened, answered. */
function Journey({ guest }: { guest: Guest }) {
    const rsvp = guest.latest_rsvp!;
    const message = rsvp.message;
    const failed = message?.status === 'failed';
    const delivered =
        message?.delivered_at ??
        (message && ['delivered', 'read'].includes(message.status)
            ? message.updated_at
            : null);
    const openedCount = guest.link_open_count ?? 0;
    const opened =
        (openedCount > 0 ? guest.link_last_opened_at : null) ??
        message?.read_at ??
        null;
    const answered = ['confirmed', 'declined', 'maybe'].includes(
        guest.rsvp_status,
    );

    const steps: {
        label: string;
        icon: LucideIcon;
        at: string | null;
        done: boolean;
        danger?: boolean;
    }[] = [
        {
            label: 'Sent',
            icon: Send,
            at: rsvp.sent_at,
            done: !!rsvp.sent_at,
        },
        failed
            ? {
                  label: 'Failed',
                  icon: AlertTriangle,
                  at: message?.updated_at ?? null,
                  done: true,
                  danger: true,
              }
            : {
                  label: 'Delivered',
                  icon: Mail,
                  at: delivered,
                  done: !!delivered,
              },
        {
            label: 'Opened',
            icon: Eye,
            at: opened,
            done: !!opened || openedCount > 0,
        },
        {
            label: answered ? replies[guest.rsvp_status].label : 'Replied',
            icon: answered ? replies[guest.rsvp_status].icon : Check,
            at: rsvp.responded_at,
            done: answered,
        },
    ];

    return (
        <Block title="Invitation">
            <div className="rounded-2xl bg-card p-4 shadow-card">
                <ol className="grid grid-cols-4">
                    {steps.map((step, index) => (
                        <li
                            key={step.label}
                            className="relative flex flex-col items-center gap-1.5 text-center"
                        >
                            {index > 0 && (
                                <span
                                    aria-hidden
                                    className={cn(
                                        'absolute top-4 right-1/2 h-0.5 w-full -translate-y-1/2',
                                        step.done
                                            ? step.danger
                                                ? 'bg-destructive'
                                                : 'bg-success'
                                            : 'bg-foreground/10',
                                    )}
                                />
                            )}
                            <span
                                className={cn(
                                    'relative flex size-8 items-center justify-center rounded-full',
                                    step.done
                                        ? step.danger
                                            ? 'bg-destructive text-white'
                                            : 'bg-success text-white'
                                        : 'bg-foreground/8 text-subtle',
                                )}
                            >
                                <step.icon
                                    className="size-4"
                                    strokeWidth={2.25}
                                />
                            </span>
                            <span
                                className={cn(
                                    'text-[11px] leading-tight font-semibold',
                                    !step.done && 'text-subtle',
                                    step.danger && 'text-destructive',
                                )}
                            >
                                {step.label}
                            </span>
                            <span className="text-[10px] leading-tight text-subtle">
                                {step.done && step.at ? timeAgo(step.at) : '—'}
                            </span>
                        </li>
                    ))}
                </ol>
                {failed && message?.error && (
                    <p className="mt-3 rounded-lg bg-destructive-muted px-3 py-2 text-xs text-destructive">
                        {message.error}
                    </p>
                )}
            </div>
        </Block>
    );
}

/** Party, table and messages, as three tiles. */
function Glance({ guest, limits }: { guest: Guest; limits?: MessageLimits }) {
    const invited =
        guest.invited_additional_guests === null &&
        guest.invited_children === null
            ? null
            : 1 +
              (guest.invited_additional_guests ?? 0) +
              (guest.invited_children ?? 0);
    const declined = guest.rsvp_status === 'declined';
    const sent = guest.messages_sent;

    return (
        <div className="grid grid-cols-3 gap-2.5">
            <Tile
                icon={Users}
                label="Party"
                value={declined ? '—' : String(guest.party_size)}
                detail={
                    declined
                        ? 'Not coming'
                        : invited !== null
                          ? `of ${invited} invited`
                          : partyText(
                                guest.additional_guests,
                                guest.children,
                            ) || 'Just them'
                }
            />
            <Tile
                icon={Sofa}
                label="Table"
                value={guest.seating?.table ?? '—'}
                detail={
                    guest.seating
                        ? `${guest.seating.seats.length === 1 ? 'Seat' : 'Seats'} ${guest.seating.seats.join(', ')}`
                        : 'Not seated'
                }
            />
            <Tile
                icon={MessageSquareText}
                label="Messages"
                value={String(
                    (sent?.invitations ?? 0) + (sent?.reminders ?? 0),
                )}
                detail={
                    sent && limits
                        ? `${Math.max(0, limits.reminders - sent.reminders)} reminders left`
                        : 'sent'
                }
            />
        </div>
    );
}

function Tile({
    icon: Icon,
    label,
    value,
    detail,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    detail: string;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-2 rounded-2xl bg-card p-3 shadow-card">
            <span className="flex items-center gap-1.5 text-[11px] font-semibold text-muted-foreground">
                <Icon className="size-3.5" strokeWidth={2} />
                {label}
            </span>
            <div className="min-w-0">
                <div className="truncate text-lg leading-tight font-bold">
                    {value}
                </div>
                <div className="truncate text-[11px] text-subtle">{detail}</div>
            </div>
        </div>
    );
}

function hasAnswers(guest: Guest, answers: GuestAnswer[]): boolean {
    return (
        replied(guest) ||
        !!guest.latest_rsvp?.response_note ||
        !!guest.company ||
        !!guest.job_title ||
        !!guest.address ||
        guest.dietary_restrictions.length > 0 ||
        !!guest.dietary_notes ||
        answers.length > 0
    );
}

/** The guest has answered the RSVP form. */
function replied(guest: Guest): boolean {
    return ['confirmed', 'declined', 'maybe'].includes(guest.rsvp_status);
}

/**
 * What the guest filled in on the RSVP form: their reply and party first
 * (as short rows), then dietary needs and every question (label over answer).
 */
function Answers({ guest, answers }: { guest: Guest; answers: GuestAnswer[] }) {
    const note = guest.latest_rsvp?.response_note;
    const coming = replied(guest) && guest.rsvp_status !== 'declined';
    const of = (value: number | null) =>
        value === null ? '' : ` of ${value} invited`;

    return (
        <div className="grid gap-px overflow-hidden rounded-2xl bg-border shadow-card">
            {replied(guest) && (
                <Fact label="Reply">{replies[guest.rsvp_status].label}</Fact>
            )}
            {coming && (
                <>
                    <Fact label="Plus-ones">
                        {guest.additional_guests}
                        <Muted>{of(guest.invited_additional_guests)}</Muted>
                    </Fact>
                    <Fact label="Children">
                        {guest.children}
                        <Muted>{of(guest.invited_children)}</Muted>
                    </Fact>
                    <Fact label="Coming in total">
                        {guest.party_size}{' '}
                        {guest.party_size === 1 ? 'person' : 'people'}
                    </Fact>
                </>
            )}
            {note && (
                <Answer label="Their note">
                    <span className="italic">“{note}”</span>
                </Answer>
            )}
            {guest.dietary_restrictions.length > 0 && (
                <Answer label="Dietary">
                    <span className="flex flex-wrap gap-1.5">
                        {guest.dietary_restrictions.map((item) => (
                            <span
                                key={item}
                                className="rounded-full bg-raised px-2.5 py-0.5 text-xs font-semibold"
                            >
                                {headline(item)}
                            </span>
                        ))}
                    </span>
                </Answer>
            )}
            {guest.dietary_notes && (
                <Answer label="Dietary notes">{guest.dietary_notes}</Answer>
            )}
            {guest.company && <Answer label="Company">{guest.company}</Answer>}
            {guest.job_title && (
                <Answer label="Job title">{guest.job_title}</Answer>
            )}
            {guest.address && <Answer label="Address">{guest.address}</Answer>}
            {answers.map((answer) => (
                <Answer key={answer.question} label={answer.question}>
                    {formatAnswer(answer.value)}
                </Answer>
            ))}
        </div>
    );
}

/** A short answer: label on the left, value on the right. */
function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-baseline justify-between gap-4 bg-card px-4 py-3 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-semibold">{children}</span>
        </div>
    );
}

function Muted({ children }: { children: ReactNode }) {
    return <span className="font-normal text-subtle">{children}</span>;
}

/** Phone and email, each with its ways to reach the guest (only what they gave). */
function Contact({ guest }: { guest: Guest }) {
    const digits = guest.phone?.replace(/\D/g, '');

    return (
        <div className="grid gap-px overflow-hidden rounded-2xl bg-border shadow-card">
            {guest.phone && (
                <ContactRow label="Phone" value={guest.phone}>
                    {digits && (
                        <ContactButton
                            href={`https://wa.me/${digits}`}
                            label="WhatsApp"
                            icon={MessageCircle}
                            className="bg-success-muted text-success"
                        />
                    )}
                    <ContactButton
                        href={`tel:${guest.phone}`}
                        label="Call"
                        icon={Phone}
                    />
                </ContactRow>
            )}
            {guest.email && (
                <ContactRow label="Email" value={guest.email}>
                    <ContactButton
                        href={`mailto:${guest.email}`}
                        label="Email"
                        icon={Mail}
                    />
                </ContactRow>
            )}
        </div>
    );
}

function ContactRow({
    label,
    value,
    children,
}: {
    label: string;
    value: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-center gap-3 bg-card px-4 py-2.5">
            <div className="min-w-0 flex-1">
                <div className="text-xs text-muted-foreground">{label}</div>
                <div className="truncate text-sm font-semibold">{value}</div>
            </div>
            <div className="flex shrink-0 gap-2">{children}</div>
        </div>
    );
}

function ContactButton({
    href,
    label,
    icon: Icon,
    className = 'bg-foreground/6 text-foreground',
}: {
    href: string;
    label: string;
    icon: LucideIcon;
    className?: string;
}) {
    return (
        <a
            href={href}
            target={href.startsWith('http') ? '_blank' : undefined}
            rel="noreferrer"
            aria-label={label}
            title={label}
            className={cn(
                'flex size-9 items-center justify-center rounded-full transition-opacity hover:opacity-80',
                className,
            )}
        >
            <Icon className="size-4" strokeWidth={2} />
        </a>
    );
}

function Answer({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="bg-card px-4 py-3">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-0.5 text-sm font-semibold wrap-break-word whitespace-pre-line">
                {children}
            </div>
        </div>
    );
}

/** The client's private notes and any messages written just for this guest. */
function Notes({ guest }: { guest: Guest }) {
    return (
        <div className="grid gap-2.5">
            {guest.notes && (
                <div className="flex gap-3 rounded-2xl bg-warning-muted p-3.5 text-sm">
                    <StickyNote className="mt-0.5 size-4 shrink-0 text-warning" />
                    <p className="wrap-break-word whitespace-pre-line">
                        {guest.notes}
                    </p>
                </div>
            )}
            {guest.invitation_message && (
                <CustomMessage
                    label="Their invitation"
                    text={guest.invitation_message}
                />
            )}
            {guest.reminder_message && (
                <CustomMessage
                    label="Their reminder"
                    text={guest.reminder_message}
                />
            )}
        </div>
    );
}

function CustomMessage({ label, text }: { label: string; text: string }) {
    return (
        <div className="rounded-2xl bg-card p-3.5 shadow-card">
            <div className="mb-1.5 text-xs text-muted-foreground">{label}</div>
            <p className="rounded-xl rounded-tl-sm bg-success-muted px-3 py-2 text-[13px] wrap-break-word whitespace-pre-line">
                {text}
            </p>
        </div>
    );
}

/** Everything that happened, folded away until asked for. */
function Activity({
    guest,
    details,
}: {
    guest: Guest;
    details: GuestDetailsData | null;
}) {
    const [open, setOpen] = useState(false);

    return (
        <section className="rounded-2xl bg-card shadow-card">
            <button
                type="button"
                aria-expanded={open}
                onClick={() => setOpen((value) => !value)}
                className="flex w-full items-center justify-between px-4 py-3.5 text-sm font-bold"
            >
                Activity
                <ChevronDown
                    className={cn(
                        'size-4.5 text-muted-foreground transition-transform',
                        open && 'rotate-180',
                    )}
                />
            </button>
            {open && (
                <div className="border-t border-border px-4 py-3.5 wrap-break-word">
                    <GuestTimeline guest={guest} details={details} />
                </div>
            )}
        </section>
    );
}

function Block({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="grid gap-2.5">
            <h3 className="px-1 text-sm font-bold">{title}</h3>
            {children}
        </section>
    );
}

/** "1 plus-one + 2 children"; only the parts above zero. */
function partyText(additional: number, children: number): string {
    return [
        additional > 0 &&
            `${additional} ${additional === 1 ? 'plus-one' : 'plus-ones'}`,
        children > 0 && `${children} ${children === 1 ? 'child' : 'children'}`,
    ]
        .filter(Boolean)
        .join(' + ');
}

function formatAnswer(value: GuestAnswer['value']): string {
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    return String(value);
}

/** gluten_free → Gluten free */
function headline(value: string): string {
    const words = value.replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}
