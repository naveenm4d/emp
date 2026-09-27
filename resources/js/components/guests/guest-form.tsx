import { useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronRight } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';

import { MessagePlaceholders } from '@/components/shared/message-placeholders';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    CounterRow,
    Field,
    POPUP,
} from '@/components/web/invitation/registration-dialog';
import { cn } from '@/lib/utils';
import type { Guest } from '@/types';

export type GuestFormData = {
    name: string;
    email: string;
    phone: string;
    notes: string;
    invitation_message: string;
    reminder_message: string;
    invited_additional_guests: number | '';
    invited_children: number | '';
};

type GuestForm = ReturnType<typeof useForm<GuestFormData>>;

type Step = 'details' | 'invitation' | 'confirm';

const STEPS: Step[] = ['details', 'invitation', 'confirm'];

const STEP_TITLES: Record<Step, string> = {
    details: 'Guest details',
    invitation: 'Party and messages',
    confirm: 'Check and save',
};

const DETAIL_FIELDS = ['name', 'email', 'phone', 'notes'];

/** Most plus-ones / children a guest can be invited with (matches GuestRules). */
const MAX_PARTY = 20;

type GuestFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The guest being edited; adds a new guest without one. */
    guest?: Guest;
    /** Line above the step title (the event's title). */
    subtitle: string;
    /** The event's invitation message, shown when the guest has no custom one. */
    defaultMessage?: string;
    /** The event's reminder message, shown when the guest has no custom one. */
    defaultReminderMessage?: string;
    /** Posts the form; call `done` from its onSuccess (unless there was a flash error). */
    onSubmit: (form: GuestForm, done: () => void) => void;
};

/**
 * Adding or editing a guest, as the EMP popup (the RSVP popup's design, in
 * the dashboard's theme): details, then the party they're invited with and
 * their messages, then a last check.
 */
export function GuestFormDialog({
    open,
    onOpenChange,
    guest,
    subtitle,
    defaultMessage,
    defaultReminderMessage,
    onSubmit,
}: GuestFormDialogProps) {
    const form = useForm<GuestFormData>({
        name: guest?.name ?? '',
        email: guest?.email ?? '',
        phone: guest?.phone ?? '',
        notes: guest?.notes ?? '',
        invitation_message: guest?.invitation_message ?? '',
        reminder_message: guest?.reminder_message ?? '',
        invited_additional_guests: guest?.invited_additional_guests ?? '',
        invited_children: guest?.invited_children ?? '',
    });
    const [step, setStep] = useState<Step>('details');
    const [showsMessages, setShowsMessages] = useState(
        !!guest?.invitation_message || !!guest?.reminder_message,
    );
    // Adding several guests in a row keeps the popup open.
    const [another, setAnother] = useState(false);
    const index = STEPS.indexOf(step);
    const id = guest?.id ?? 'new';
    const usesEventParty =
        form.data.invited_additional_guests === '' &&
        form.data.invited_children === '';

    /** The event's text as this guest would get it (their name filled in). */
    const forGuest = (text?: string) =>
        text?.replace(
            /\{\{\s*guest\.name\s*\}\}/g,
            form.data.name || 'guest name',
        );

    const back = () =>
        index > 0 ? setStep(STEPS[index - 1]) : onOpenChange(false);

    const save = (addAnother: boolean) => {
        setAnother(addAnother);
        form.clearErrors();
        onSubmit(form, () => {
            if (addAnother) {
                form.reset();
                setShowsMessages(false);
                setStep('details');
            } else {
                onOpenChange(false);
            }
        });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (step !== 'confirm') {
            setStep(STEPS[index + 1]);

            return;
        }

        save(false);
    };

    // Validation errors send the client back to the step with the field.
    useEffect(() => {
        const fields = Object.keys(form.errors);

        if (fields.length > 0) {
            setStep(
                fields.some((key) => DETAIL_FIELDS.includes(key))
                    ? 'details'
                    : 'invitation',
            );
        }
    }, [form.errors]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={POPUP}>
                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                    noValidate
                >
                    <div className="flex flex-col gap-3 px-4 pt-5 pr-12">
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={back}
                                aria-label={index > 0 ? 'Back' : 'Close'}
                                className="flex size-9 shrink-0 items-center justify-center rounded-full bg-card shadow-card"
                            >
                                <ArrowLeft className="size-4.5" />
                            </button>
                            <div className="min-w-0 flex-1">
                                <DialogDescription className="truncate text-xs">
                                    {subtitle} ·{' '}
                                    {guest
                                        ? `Editing ${guest.name}`
                                        : 'New guest'}
                                </DialogDescription>
                                <DialogTitle className="text-base font-bold">
                                    {STEP_TITLES[step]}
                                </DialogTitle>
                            </div>
                            <span className="text-xs font-semibold text-muted-foreground">
                                {index + 1} of {STEPS.length}
                            </span>
                        </div>
                        <div className="flex gap-1" aria-hidden>
                            {STEPS.map((item, position) => (
                                <span
                                    key={item}
                                    className={cn(
                                        'h-1 flex-1 rounded-full',
                                        position <= index
                                            ? 'bg-primary'
                                            : 'bg-foreground/14',
                                    )}
                                />
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-5 overflow-y-auto px-4 pt-5 pb-5">
                        {step === 'details' && (
                            <>
                                <Field
                                    id={`name-${id}`}
                                    label="Name"
                                    required
                                    error={form.errors.name}
                                >
                                    <Input
                                        id={`name-${id}`}
                                        autoFocus
                                        autoComplete="off"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    id={`phone-${id}`}
                                    label="Phone (WhatsApp)"
                                    error={form.errors.phone}
                                    hint="Invitations and reminders go to this number."
                                >
                                    <Input
                                        id={`phone-${id}`}
                                        type="tel"
                                        placeholder="+94 77 123 4567"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    id={`email-${id}`}
                                    label="Email"
                                    error={form.errors.email}
                                    hint="Add a phone or an email, so you can reach them."
                                >
                                    <Input
                                        id={`email-${id}`}
                                        type="email"
                                        value={form.data.email}
                                        onChange={(e) =>
                                            form.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    id={`notes-${id}`}
                                    label="Notes (only you see these)"
                                    error={form.errors.notes}
                                >
                                    <Textarea
                                        id={`notes-${id}`}
                                        rows={2}
                                        placeholder="e.g. Bride's cousin, needs parking"
                                        value={form.data.notes}
                                        onChange={(e) =>
                                            form.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </>
                        )}

                        {step === 'invitation' && (
                            <>
                                <section className="grid gap-2.5">
                                    <span className="text-sm font-bold">
                                        Invited with
                                    </span>
                                    <div className="rounded-xl bg-card shadow-card">
                                        <label className="flex items-center justify-between gap-3 border-b border-border px-3.5 py-3 text-sm font-semibold last:border-b-0">
                                            <span>
                                                Use the event’s settings
                                                <span className="block text-xs font-normal text-subtle">
                                                    Plus-ones and children as
                                                    the RSVP form allows
                                                </span>
                                            </span>
                                            <input
                                                type="checkbox"
                                                role="switch"
                                                checked={usesEventParty}
                                                onChange={(e) =>
                                                    form.setData((data) => ({
                                                        ...data,
                                                        invited_additional_guests:
                                                            e.target.checked
                                                                ? ''
                                                                : 0,
                                                        invited_children: e
                                                            .target.checked
                                                            ? ''
                                                            : 0,
                                                    }))
                                                }
                                                className="peer sr-only"
                                            />
                                            <span className="relative h-7 w-11.5 shrink-0 rounded-full bg-foreground/14 transition-colors peer-checked:bg-strong peer-focus-visible:ring-3 peer-focus-visible:ring-ring/50 after:absolute after:top-0.75 after:left-0.75 after:size-5.5 after:rounded-full after:bg-card after:transition-transform peer-checked:after:translate-x-4.5" />
                                        </label>
                                        {!usesEventParty && (
                                            <>
                                                <CounterRow
                                                    label="Plus-ones"
                                                    hint={
                                                        form.data
                                                            .invited_additional_guests ===
                                                        0
                                                            ? 'Just them'
                                                            : undefined
                                                    }
                                                    value={Number(
                                                        form.data
                                                            .invited_additional_guests ||
                                                            0,
                                                    )}
                                                    max={MAX_PARTY}
                                                    onChange={(value) =>
                                                        form.setData(
                                                            'invited_additional_guests',
                                                            value,
                                                        )
                                                    }
                                                />
                                                <CounterRow
                                                    label="Children"
                                                    value={Number(
                                                        form.data
                                                            .invited_children ||
                                                            0,
                                                    )}
                                                    max={MAX_PARTY}
                                                    onChange={(value) =>
                                                        form.setData(
                                                            'invited_children',
                                                            value,
                                                        )
                                                    }
                                                />
                                            </>
                                        )}
                                    </div>
                                    {(form.errors.invited_additional_guests ??
                                        form.errors.invited_children) && (
                                        <p className="text-xs text-destructive">
                                            {form.errors
                                                .invited_additional_guests ??
                                                form.errors.invited_children}
                                        </p>
                                    )}
                                </section>

                                <section className="grid gap-2.5">
                                    <button
                                        type="button"
                                        aria-expanded={showsMessages}
                                        onClick={() =>
                                            setShowsMessages((shown) => !shown)
                                        }
                                        className="flex items-center justify-between rounded-xl bg-card px-3.5 py-3 text-left text-sm font-semibold shadow-card"
                                    >
                                        <span>
                                            Custom messages
                                            <span className="block text-xs font-normal text-subtle">
                                                {form.data.invitation_message ||
                                                form.data.reminder_message
                                                    ? 'Set for this guest'
                                                    : 'Optional: otherwise they get the event’s messages'}
                                            </span>
                                        </span>
                                        <ChevronRight
                                            className={cn(
                                                'size-4.5 text-muted-foreground transition-transform',
                                                showsMessages && 'rotate-90',
                                            )}
                                        />
                                    </button>
                                    {showsMessages && (
                                        <div className="grid gap-5 pt-1">
                                            <Field
                                                id={`invitation_message-${id}`}
                                                label="Invitation"
                                                error={
                                                    form.errors
                                                        .invitation_message
                                                }
                                                hint="Leave empty to send the event’s invitation."
                                            >
                                                <Textarea
                                                    id={`invitation_message-${id}`}
                                                    rows={3}
                                                    placeholder={forGuest(
                                                        defaultMessage,
                                                    )}
                                                    value={
                                                        form.data
                                                            .invitation_message
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'invitation_message',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <MessagePlaceholders
                                                    message={
                                                        form.data
                                                            .invitation_message
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                id={`reminder_message-${id}`}
                                                label="Reminder"
                                                error={
                                                    form.errors.reminder_message
                                                }
                                                hint="Leave empty to send the event’s reminder."
                                            >
                                                <Textarea
                                                    id={`reminder_message-${id}`}
                                                    rows={3}
                                                    placeholder={forGuest(
                                                        defaultReminderMessage,
                                                    )}
                                                    value={
                                                        form.data
                                                            .reminder_message
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'reminder_message',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                <MessagePlaceholders
                                                    message={
                                                        form.data
                                                            .reminder_message
                                                    }
                                                />
                                            </Field>
                                        </div>
                                    )}
                                </section>
                            </>
                        )}

                        {step === 'confirm' && <Summary data={form.data} />}
                    </div>

                    <div className="flex flex-col gap-2 border-t border-border px-4 pt-3 pb-6 sm:pb-4">
                        <Button
                            type="submit"
                            variant="strong"
                            className="h-13 rounded-full text-base"
                            disabled={
                                (step === 'details' &&
                                    !form.data.name.trim()) ||
                                form.processing
                            }
                        >
                            {step !== 'confirm'
                                ? 'Continue'
                                : form.processing && !another
                                  ? 'Saving…'
                                  : guest
                                    ? 'Save changes'
                                    : 'Add guest'}
                        </Button>
                        {step === 'confirm' && !guest && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-11 rounded-full"
                                disabled={form.processing}
                                onClick={() => save(true)}
                            >
                                {form.processing && another
                                    ? 'Saving…'
                                    : 'Add and add another'}
                            </Button>
                        )}
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** The last check before saving (the RSVP popup's summary card). */
function Summary({ data }: { data: GuestFormData }) {
    const plusOnes = Number(data.invited_additional_guests || 0);
    const children = Number(data.invited_children || 0);
    const party =
        data.invited_additional_guests === '' && data.invited_children === ''
            ? 'The event’s settings'
            : [
                  'The guest',
                  plusOnes > 0 &&
                      `${plusOnes} ${plusOnes === 1 ? 'plus-one' : 'plus-ones'}`,
                  children > 0 &&
                      `${children} ${children === 1 ? 'child' : 'children'}`,
              ]
                  .filter(Boolean)
                  .join(' + ');

    const rows: [string, string][] = [
        ['Name', data.name || '—'],
        ['Phone', data.phone || '—'],
        ['Email', data.email || '—'],
        ['Invited with', party],
        ['Invitation', data.invitation_message ? 'Custom' : 'Event’s message'],
        ['Reminder', data.reminder_message ? 'Custom' : 'Event’s message'],
        ...(data.notes ? ([['Notes', data.notes]] as [string, string][]) : []),
    ];

    return (
        <dl className="rounded-xl bg-card shadow-card">
            {rows.map(([label, value]) => (
                <div
                    key={label}
                    className="flex justify-between gap-4 border-b border-border px-3.5 py-3 text-sm last:border-b-0"
                >
                    <dt className="shrink-0 text-muted-foreground">{label}</dt>
                    <dd className="min-w-0 text-right font-semibold wrap-break-word">
                        {value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
