import { Check, Copy, Mail, MessageCircle, Phone } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { GuestActions } from '@/components/guests/guest-actions';
import { GuestTimeline } from '@/components/guests/guest-timeline';
import { MessageStatus } from '@/components/rsvps/message-status';
import { RsvpStatus } from '@/components/rsvps/rsvp-status';
import { StatusWithTime } from '@/components/shared/status-with-time';
import {
    Sheet,
    SheetBody,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDateTime } from '@/lib/format';
import type { Guest, RegistrationType } from '@/types';

const sourceLabels: Record<Guest['source'], string> = {
    manual: 'Added by you',
    public_link: 'Registered through the public link',
    import: 'Imported',
    api: 'Added via API',
};

type GuestDetailsSheetProps = {
    /** The selected guest (from the refreshed list, so statuses stay live); null closes the sheet. */
    guest: Guest | null;
    onClose: () => void;
    registrationType: RegistrationType;
    /** Show approval in the status grid (see showsApproval). */
    showsApproval: boolean;
    onEdit: (guest: Guest) => void;
};

/**
 * Everything about a guest: statuses with their times, their personal link,
 * details and the full timeline of what happened, including every message.
 */
export function GuestDetailsSheet({
    guest,
    onClose,
    registrationType,
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

    const rsvp = shown?.latest_rsvp;

    return (
        <Sheet
            open={guest !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <SheetContent>
                {shown && (
                    <>
                        <SheetHeader>
                            <SheetTitle className="text-lg">
                                {shown.name}
                            </SheetTitle>
                            <SheetDescription render={<div />}>
                                <ContactLinks guest={shown} />
                            </SheetDescription>
                            <div className="mt-2 flex justify-start">
                                <GuestActions
                                    guest={shown}
                                    registrationType={registrationType}
                                    onEdit={() => onEdit(shown)}
                                />
                            </div>
                        </SheetHeader>
                        <SheetBody className="space-y-6">
                            <Section title="Status">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {showsApproval && (
                                        <Field label="Approval">
                                            <StatusWithTime
                                                status={shown.approval_status}
                                                at={
                                                    shown.approval_status_changed_at
                                                }
                                            />
                                        </Field>
                                    )}
                                    <Field label="RSVP">
                                        <StatusWithTime
                                            status={shown.rsvp_status}
                                            at={
                                                shown.rsvp_status === 'pending'
                                                    ? rsvp?.sent_at
                                                    : rsvp?.responded_at
                                            }
                                        />
                                    </Field>
                                    <Field label="RSVP link">
                                        {rsvp ? (
                                            <RsvpStatus
                                                rsvp={rsvp}
                                                opened={{
                                                    count:
                                                        shown.link_open_count ??
                                                        0,
                                                    at:
                                                        shown.link_last_opened_at ??
                                                        null,
                                                }}
                                            />
                                        ) : (
                                            <Muted>Not invited yet</Muted>
                                        )}
                                    </Field>
                                    <Field label="Latest message">
                                        <MessageStatus
                                            message={rsvp?.message}
                                        />
                                        {rsvp?.message?.status === 'failed' &&
                                            rsvp.message.error && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {rsvp.message.error}
                                                </p>
                                            )}
                                    </Field>
                                </div>
                            </Section>

                            {shown.link_url && (
                                <Section title="Personal link">
                                    <CopyField value={shown.link_url} />
                                    <p className="mt-1.5 text-xs text-muted-foreground">
                                        Opens their invitation and RSVP. It
                                        stays the same when you re-invite them.
                                    </p>
                                </Section>
                            )}

                            <Section title="Details">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Field label="Source">
                                        {sourceLabels[shown.source]}
                                    </Field>
                                    <Field label="Added">
                                        {formatDateTime(shown.created_at)}
                                    </Field>
                                    {shown.notes && (
                                        <Field label="Notes" wide>
                                            <p className="whitespace-pre-line">
                                                {shown.notes}
                                            </p>
                                        </Field>
                                    )}
                                    <Field label="Invitation message" wide>
                                        <CustomText
                                            text={shown.invitation_message}
                                        />
                                    </Field>
                                    <Field label="Reminder message" wide>
                                        <CustomText
                                            text={shown.reminder_message}
                                        />
                                    </Field>
                                </div>
                            </Section>

                            <Section title="Timeline">
                                <GuestTimeline
                                    guest={shown}
                                    refreshKey={[
                                        shown.approval_status,
                                        shown.rsvp_status,
                                        rsvp?.id,
                                        rsvp?.status,
                                        rsvp?.reminder_count,
                                        rsvp?.message?.status,
                                    ].join('|')}
                                />
                            </Section>
                        </SheetBody>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}

function ContactLinks({ guest }: { guest: Guest }) {
    const digits = guest.phone?.replace(/\D/g, '');

    if (!guest.phone && !guest.email) {
        return <Muted>No contact details</Muted>;
    }

    return (
        <span className="flex flex-wrap gap-x-4 gap-y-1.5">
            {guest.phone && (
                <a
                    href={`tel:${guest.phone}`}
                    className="inline-flex items-center gap-1.5 hover:text-foreground"
                >
                    <Phone className="size-3.5" />
                    {guest.phone}
                </a>
            )}
            {digits && (
                <a
                    href={`https://wa.me/${digits}`}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1.5 hover:text-foreground"
                >
                    <MessageCircle className="size-3.5" />
                    WhatsApp
                </a>
            )}
            {guest.email && (
                <a
                    href={`mailto:${guest.email}`}
                    className="inline-flex min-w-0 items-center gap-1.5 break-all hover:text-foreground"
                >
                    <Mail className="size-3.5 shrink-0" />
                    {guest.email}
                </a>
            )}
        </span>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section>
            <h3 className="mb-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </h3>
            {children}
        </section>
    );
}

function Field({
    label,
    children,
    wide,
}: {
    label: string;
    children: ReactNode;
    wide?: boolean;
}) {
    return (
        <div className={wide ? 'sm:col-span-2' : undefined}>
            <p className="mb-1 text-xs text-muted-foreground">{label}</p>
            <div className="text-sm">{children}</div>
        </div>
    );
}

function Muted({ children }: { children: ReactNode }) {
    return <span className="text-sm text-muted-foreground">{children}</span>;
}

function CustomText({ text }: { text: string | null }) {
    return text ? (
        <p className="rounded-lg bg-muted/50 p-2.5 text-xs break-words whitespace-pre-line">
            {text}
        </p>
    ) : (
        <Muted>Uses the event's message</Muted>
    );
}

/** A read-only value with a copy button that confirms the copy. */
function CopyField({ value }: { value: string }) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timer = setTimeout(() => setCopied(false), 1500);

        return () => clearTimeout(timer);
    }, [copied]);

    return (
        <div className="flex items-center gap-2">
            <code className="min-w-0 flex-1 truncate rounded-lg bg-muted px-3 py-2 text-xs">
                {value}
            </code>
            <button
                type="button"
                onClick={() =>
                    navigator.clipboard
                        .writeText(value)
                        .then(() => setCopied(true))
                }
                className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-border transition-colors hover:bg-muted"
                aria-label="Copy link"
            >
                {copied ? (
                    <Check className="size-4 text-emerald-600" />
                ) : (
                    <Copy className="size-4" />
                )}
            </button>
        </div>
    );
}
