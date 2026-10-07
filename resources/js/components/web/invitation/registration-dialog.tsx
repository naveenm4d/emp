import { useForm } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    Check,
    CircleHelp,
    HeartCrack,
    Minus,
    PartyPopper,
    Plus,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type {
    AnswerValue,
    ContactField,
    RegistrationForm,
    RegistrationQuestion,
    RegistrationResponse,
} from '@/types/web';

/**
 * register: public sign-up (name + contact + details).
 * rsvp:     a guest's answer from their personal link.
 * preview:  what guests will see; nothing is sent.
 */
export type RegistrationDialogMode = 'register' | 'rsvp' | 'preview';

export type Attendance = 'accepted' | 'maybe' | 'declined';

type FormData = {
    name: string;
    email: string;
    phone: string;
    address: string;
    company: string;
    job_title: string;
    additional_guests: number;
    children: number;
    dietary_restrictions: string[];
    dietary_notes: string;
    answers: Record<string, AnswerValue>;
    note: string;
};

type Step = 'details' | 'response' | 'confirm';

const contactFields: {
    key: ContactField;
    label: string;
    type?: string;
    autoComplete: string;
}[] = [
    { key: 'email', label: 'Email', type: 'email', autoComplete: 'email' },
    { key: 'phone', label: 'Phone', type: 'tel', autoComplete: 'tel' },
    { key: 'company', label: 'Company', autoComplete: 'organization' },
    {
        key: 'job_title',
        label: 'Job title',
        autoComplete: 'organization-title',
    },
    { key: 'address', label: 'Address', autoComplete: 'street-address' },
];

const detailKeys = ['name', ...contactFields.map((field) => field.key)];

type RegistrationDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: RegistrationDialogMode;
    form: RegistrationForm;
    /** Where to post; null in preview. */
    url: string | null;
    /** Line above the step title: "Nimali & Kasun · 14 Nov". */
    subtitle: string;
    /** Extra line on the details step (e.g. "Registrations are approved by the host."). */
    note?: string;
    /** RSVP only: what the guest answered before. */
    initial?: RegistrationResponse | null;
    initialAttendance?: Attendance | null;
    /** Called after the server accepted the form (not for flash errors). */
    onSaved?: () => void;
    /**
     * Render the popup's content in place (the RSVP form tab's live preview)
     * instead of as a dialog; `open` / `onOpenChange` are then ignored.
     */
    inline?: boolean;
    className?: string;
};

const STEP_TITLES: Record<Step, string> = {
    details: 'Your details',
    response: 'Your response',
    confirm: 'Check and send',
};

/**
 * The guest's reply as the EMP popup (2b), opened from the template's one
 * RSVP button: attending or not with the party and the event's questions,
 * then their details (when asked, not when declining), then a last check. Portals to <body>, outside the
 * invitation's shadow root, in the fixed Nocturne palette.
 */
export function RegistrationDialog({
    open,
    onOpenChange,
    mode,
    form: config,
    url,
    subtitle,
    note,
    initial,
    initialAttendance,
    onSaved,
    inline = false,
    className,
}: RegistrationDialogProps) {
    const form = useForm<FormData>({
        name: '',
        email: initial?.email ?? '',
        phone: initial?.phone ?? '',
        address: initial?.address ?? '',
        company: initial?.company ?? '',
        job_title: initial?.job_title ?? '',
        additional_guests: initial?.additional_guests ?? 0,
        children: initial?.children ?? 0,
        dietary_restrictions: initial?.dietary_restrictions ?? [],
        dietary_notes: initial?.dietary_notes ?? '',
        answers: initial?.answers ?? {},
        note: '',
    });

    // Registering means attending; an RSVP starts with the question.
    const [attendance, setAttendance] = useState<Attendance | null>(
        mode === 'register' ? 'accepted' : (initialAttendance ?? null),
    );

    const shownContact = contactFields.filter(
        (field) => config.contact[field.key] !== 'off',
    );
    // The response comes first; details follow (not when declining: a
    // decline only sends the answer and a note).
    const steps: Step[] = [
        'response',
        ...((mode === 'register' || shownContact.length > 0) &&
        attendance !== 'declined'
            ? (['details'] as const)
            : []),
        'confirm',
    ];
    const [chosenStep, setStep] = useState<Step>(steps[0]);
    // The live preview's settings can drop a step (like the details) while it's shown.
    const step = steps.includes(chosenStep) ? chosenStep : steps[0];
    const index = steps.indexOf(step);

    const error = (key: string) =>
        form.errors[key as keyof FormData] ??
        Object.entries(form.errors).find(([field]) =>
            field.startsWith(`${key}.`),
        )?.[1];

    const attending = attendance === 'accepted' || attendance === 'maybe';
    // Children are asked once the guest brings someone (or when there are no plus-ones to pick).
    const justMe = config.plus_ones && form.data.additional_guests === 0;
    const asksChildren = config.children && !justMe;
    const partySize =
        1 +
        (config.plus_ones ? form.data.additional_guests : 0) +
        (asksChildren ? form.data.children : 0);
    const preview = mode === 'preview' || !url;

    const back = () =>
        index > 0 ? setStep(steps[index - 1]) : !inline && onOpenChange(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (step !== 'confirm') {
            setStep(steps[index + 1]);

            return;
        }

        if (preview || !attendance) {
            return;
        }

        form.transform((data) =>
            attendance === 'declined'
                ? { attendance, note: data.note }
                : {
                      ...data,
                      // "Just me" comes alone, so without children.
                      children: justMe ? 0 : data.children,
                      ...(mode === 'rsvp' ? { attendance } : {}),
                  },
        );
        form.post(url, {
            preserveScroll: true,
            onError: (errors) =>
                setStep(
                    Object.keys(errors).some((key) =>
                        detailKeys.includes(key),
                    ) && steps.includes('details')
                        ? 'details'
                        : 'response',
                ),
            onSuccess: (page) => {
                // Domain errors (full, closed, duplicate, expired) come back as a flash error.
                if (!page.props.flash.error) {
                    onOpenChange(false);
                    onSaved?.();
                }
            },
        });
    };

    const setAnswer = (id: string, value: AnswerValue) =>
        form.setData('answers', { ...form.data.answers, [id]: value });

    const toggle = (list: string[], value: string, on: boolean) =>
        on ? [...list, value] : list.filter((item) => item !== value);

    const continueLabel =
        step === 'confirm'
            ? form.processing
                ? 'Sending…'
                : mode === 'register'
                  ? 'Register'
                  : 'Send reply'
            : `Continue${step === 'response' && attending && partySize > 1 ? ` · ${partySize} guests` : ''}`;

    const Title = inline ? 'h2' : DialogTitle;
    const Description = inline ? 'p' : DialogDescription;

    const content = (
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
                        disabled={inline && index === 0}
                        className="flex size-9 shrink-0 items-center justify-center rounded-full bg-card shadow-card disabled:opacity-40"
                    >
                        <ArrowLeft className="size-4.5" />
                    </button>
                    <div className="min-w-0 flex-1">
                        <Description className="truncate text-xs text-muted-foreground">
                            {subtitle}
                        </Description>
                        <Title className="text-base font-bold">
                            {STEP_TITLES[step]}
                        </Title>
                    </div>
                    <span className="text-xs font-semibold text-muted-foreground">
                        {index + 1} of {steps.length}
                    </span>
                </div>
                <div className="flex gap-1" aria-hidden>
                    {steps.map((item, position) => (
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
                        {note && (
                            <p className="text-xs text-muted-foreground">
                                {note}
                            </p>
                        )}
                        {mode === 'register' && (
                            <Field
                                id="reg-name"
                                label="Name"
                                required
                                error={form.errors.name}
                            >
                                <Input
                                    id="reg-name"
                                    autoComplete="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                />
                            </Field>
                        )}
                        {shownContact.map((field) => (
                            <Field
                                key={field.key}
                                id={`reg-${field.key}`}
                                label={field.label}
                                required={
                                    config.contact[field.key] === 'required'
                                }
                                error={error(field.key)}
                            >
                                <Input
                                    id={`reg-${field.key}`}
                                    type={field.type ?? 'text'}
                                    autoComplete={field.autoComplete}
                                    value={form.data[field.key]}
                                    onChange={(e) =>
                                        form.setData(field.key, e.target.value)
                                    }
                                />
                            </Field>
                        ))}
                    </>
                )}

                {step === 'response' && (
                    <>
                        {mode !== 'register' && (
                            <section className="grid gap-2.5">
                                <span className="text-sm font-bold">
                                    Will you attend?
                                </span>
                                <div
                                    role="radiogroup"
                                    aria-label="Will you attend?"
                                    className={cn(
                                        'grid gap-2.5',
                                        config.allow_maybe
                                            ? 'grid-cols-3'
                                            : 'grid-cols-2',
                                    )}
                                >
                                    <AttendCard
                                        icon={PartyPopper}
                                        label="Joyfully accept"
                                        checked={attendance === 'accepted'}
                                        onSelect={() =>
                                            setAttendance('accepted')
                                        }
                                    />
                                    {config.allow_maybe && (
                                        <AttendCard
                                            icon={CircleHelp}
                                            label="Not sure yet"
                                            checked={attendance === 'maybe'}
                                            onSelect={() =>
                                                setAttendance('maybe')
                                            }
                                        />
                                    )}
                                    <AttendCard
                                        icon={HeartCrack}
                                        label="Regretfully decline"
                                        checked={attendance === 'declined'}
                                        onSelect={() =>
                                            setAttendance('declined')
                                        }
                                    />
                                </div>
                                <FieldError message={error('attendance')} />
                            </section>
                        )}

                        {attendance === 'declined' && (
                            <Field
                                id="decline-note"
                                label="Note to the host (optional)"
                                error={error('note')}
                            >
                                <Textarea
                                    id="decline-note"
                                    rows={3}
                                    maxLength={500}
                                    placeholder="e.g. Sorry, I'll be travelling that week."
                                    value={form.data.note}
                                    onChange={(e) =>
                                        form.setData('note', e.target.value)
                                    }
                                />
                            </Field>
                        )}

                        {attending && (
                            <AttendingDetails
                                config={config}
                                data={form.data}
                                asksChildren={asksChildren}
                                error={error}
                                setData={form.setData}
                                setAnswer={setAnswer}
                                toggle={toggle}
                            />
                        )}
                    </>
                )}

                {step === 'confirm' && attendance && (
                    <Summary
                        attendance={attendance}
                        partySize={partySize}
                        data={form.data}
                        config={config}
                    />
                )}
            </div>

            <div className="flex flex-col gap-2 border-t border-border px-4 pt-3 pb-6 sm:pb-4">
                {preview && step === 'confirm' && (
                    <p className="text-center text-xs text-muted-foreground">
                        Preview: nothing is sent.
                    </p>
                )}
                <Button
                    type="submit"
                    variant="strong"
                    className="h-13 rounded-full text-base"
                    disabled={
                        (step === 'response' && !attendance) ||
                        (step === 'confirm' && (preview || form.processing))
                    }
                >
                    {continueLabel}
                </Button>
            </div>
        </form>
    );

    if (inline) {
        return (
            <div
                className={cn(
                    'nocturne flex flex-col overflow-hidden bg-background font-[Inter,ui-sans-serif,system-ui,sans-serif] text-sm text-foreground',
                    className,
                )}
            >
                {content}
            </div>
        );
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className={NOCTURNE_POPUP}>{content}</DialogContent>
        </Dialog>
    );
}

/** The party and the event's questions, for guests who are coming. */
function AttendingDetails({
    config,
    data,
    asksChildren,
    error,
    setData,
    setAnswer,
    toggle,
}: {
    config: RegistrationForm;
    data: FormData;
    asksChildren: boolean;
    error: (key: string) => string | undefined;
    setData: <K extends keyof FormData>(key: K, value: FormData[K]) => void;
    setAnswer: (id: string, value: AnswerValue) => void;
    toggle: (list: string[], value: string, on: boolean) => string[];
}) {
    return (
        <>
            {(config.plus_ones || config.children) && (
                <section className="grid gap-2.5">
                    <div className="flex items-baseline justify-between">
                        <span className="text-sm font-bold">Who’s coming?</span>
                        {config.plus_ones && (
                            <span className="text-xs text-muted-foreground">
                                Up to {config.max_additional_guests + 1}{' '}
                                including you
                            </span>
                        )}
                    </div>
                    <div className="rounded-xl bg-card shadow-card">
                        {config.plus_ones && (
                            <CounterRow
                                label="Guests with you"
                                hint={
                                    data.additional_guests === 0
                                        ? 'Just me'
                                        : undefined
                                }
                                value={data.additional_guests}
                                max={config.max_additional_guests}
                                onChange={(value) =>
                                    setData('additional_guests', value)
                                }
                            />
                        )}
                        {asksChildren && (
                            <CounterRow
                                label="Children"
                                value={data.children}
                                max={config.max_children}
                                onChange={(value) => setData('children', value)}
                            />
                        )}
                    </div>
                    <FieldError
                        message={
                            error('additional_guests') ?? error('children')
                        }
                    />
                </section>
            )}

            {config.dietary_options.length > 0 && (
                <fieldset className="grid gap-2">
                    <legend className="mb-2.5 text-sm font-bold">
                        Dietary restrictions
                    </legend>
                    <div className="flex flex-wrap gap-2">
                        {config.dietary_options.map((option) => (
                            <ChoiceChip
                                key={option.value}
                                type="checkbox"
                                label={option.label}
                                checked={data.dietary_restrictions.includes(
                                    option.value,
                                )}
                                onChange={(checked) =>
                                    setData(
                                        'dietary_restrictions',
                                        toggle(
                                            data.dietary_restrictions,
                                            option.value,
                                            checked,
                                        ),
                                    )
                                }
                            />
                        ))}
                    </div>
                    <FieldError message={error('dietary_restrictions')} />
                </fieldset>
            )}

            {config.dietary_notes && (
                <Field
                    id="reg-dietary-notes"
                    label="Dietary notes"
                    error={error('dietary_notes')}
                >
                    <Textarea
                        id="reg-dietary-notes"
                        rows={2}
                        value={data.dietary_notes}
                        onChange={(e) =>
                            setData('dietary_notes', e.target.value)
                        }
                    />
                </Field>
            )}

            {config.questions.map((question) => (
                <QuestionField
                    key={question.id}
                    question={question}
                    value={data.answers[question.id] ?? null}
                    error={error(`answers.${question.id}`)}
                    onChange={(value) => setAnswer(question.id, value)}
                    toggle={toggle}
                />
            ))}
        </>
    );
}

/** The last check before sending. */
function Summary({
    attendance,
    partySize,
    data,
    config,
}: {
    attendance: Attendance;
    partySize: number;
    data: FormData;
    config: RegistrationForm;
}) {
    const rows: [string, string][] = [];

    if (attendance === 'declined') {
        rows.push(['Reply', 'Can’t make it']);

        if (data.note) {
            rows.push(['Note', data.note]);
        }
    } else {
        rows.push([
            'Reply',
            attendance === 'maybe' ? 'Not sure yet' : 'Attending',
        ]);
        rows.push([
            'Party',
            `${partySize} ${partySize === 1 ? 'guest' : 'guests'}`,
        ]);

        const dietary = config.dietary_options
            .filter((option) =>
                data.dietary_restrictions.includes(option.value),
            )
            .map((option) => option.label);

        if (dietary.length > 0) {
            rows.push(['Dietary', dietary.join(', ')]);
        }

        for (const question of config.questions) {
            const value = data.answers[question.id];

            if (value !== null && value !== undefined && value !== '') {
                rows.push([
                    question.label,
                    Array.isArray(value)
                        ? value.join(', ')
                        : typeof value === 'boolean'
                          ? value
                              ? 'Yes'
                              : 'No'
                          : String(value),
                ]);
            }
        }
    }

    return (
        <dl className="rounded-xl bg-card shadow-card">
            {rows.map(([label, value]) => (
                <div
                    key={label}
                    className="flex justify-between gap-4 border-b border-border px-3.5 py-3 text-sm last:border-b-0"
                >
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="text-right font-semibold">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

/** "Joyfully accept" / "Regretfully decline" choice (2b). */
function AttendCard({
    icon: Icon,
    label,
    checked,
    onSelect,
}: {
    icon: LucideIcon;
    label: string;
    checked: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={checked}
            onClick={onSelect}
            className={cn(
                'relative flex flex-col gap-1.5 rounded-xl bg-card p-3.5 text-left text-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                checked
                    ? 'border-2 border-strong font-bold'
                    : 'border border-input font-semibold text-muted-foreground',
            )}
        >
            <Icon
                className={cn('size-6', checked ? 'text-link' : '')}
                strokeWidth={1.75}
            />
            {label}
            {checked && (
                <span className="absolute top-3 right-3 flex size-5 items-center justify-center rounded-full bg-strong text-strong-foreground">
                    <Check className="size-3.5" />
                </span>
            )}
        </button>
    );
}

function QuestionField({
    question,
    value,
    error,
    onChange,
    toggle,
}: {
    question: RegistrationQuestion;
    value: AnswerValue;
    error?: string;
    onChange: (value: AnswerValue) => void;
    toggle: (list: string[], value: string, on: boolean) => string[];
}) {
    const id = `question-${question.id}`;
    const options = question.options ?? [];

    if (question.type === 'checkbox') {
        return (
            <div className="grid gap-1.5">
                <label className="flex items-center justify-between gap-3 rounded-xl bg-card px-3.5 py-3 text-sm font-semibold shadow-card">
                    <span>
                        {question.label}
                        {question.required && <Required />}
                    </span>
                    <input
                        id={id}
                        type="checkbox"
                        role="switch"
                        checked={value === true}
                        onChange={(e) => onChange(e.target.checked)}
                        className="peer sr-only"
                    />
                    <span className="relative h-7 w-11.5 shrink-0 rounded-full bg-foreground/14 transition-colors peer-checked:bg-strong peer-focus-visible:ring-3 peer-focus-visible:ring-ring/50 after:absolute after:top-0.75 after:left-0.75 after:size-5.5 after:rounded-full after:bg-card after:transition-transform peer-checked:after:translate-x-4.5" />
                </label>
                <FieldError message={error} />
            </div>
        );
    }

    if (question.type === 'radio' || question.type === 'multi_select') {
        const selected = Array.isArray(value) ? value : [];

        return (
            <fieldset className="grid gap-2">
                <legend className="mb-2.5 text-sm font-bold">
                    {question.label}
                    {question.required && <Required />}
                </legend>
                <div className="flex flex-wrap gap-2">
                    {options.map((option) =>
                        question.type === 'radio' ? (
                            <ChoiceChip
                                key={option}
                                type="radio"
                                name={id}
                                label={option}
                                checked={value === option}
                                onChange={() => onChange(option)}
                            />
                        ) : (
                            <ChoiceChip
                                key={option}
                                type="checkbox"
                                label={option}
                                checked={selected.includes(option)}
                                onChange={(checked) =>
                                    onChange(toggle(selected, option, checked))
                                }
                            />
                        ),
                    )}
                </div>
                <FieldError message={error} />
            </fieldset>
        );
    }

    return (
        <Field
            id={id}
            label={question.label}
            required={question.required}
            error={error}
        >
            {question.type === 'select' ? (
                <Select
                    id={id}
                    value={typeof value === 'string' ? value : ''}
                    onChange={(e) => onChange(e.target.value || null)}
                >
                    <option value="">Choose…</option>
                    {options.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </Select>
            ) : question.type === 'textarea' ? (
                <Textarea
                    id={id}
                    rows={3}
                    value={typeof value === 'string' ? value : ''}
                    onChange={(e) => onChange(e.target.value)}
                />
            ) : (
                <Input
                    id={id}
                    type={question.type === 'number' ? 'number' : 'text'}
                    value={
                        typeof value === 'string' || typeof value === 'number'
                            ? value
                            : ''
                    }
                    onChange={(e) => onChange(e.target.value)}
                />
            )}
        </Field>
    );
}

/** A labelled popup field (bold label, error or hint under it). */
export function Field({
    id,
    label,
    required,
    hint,
    error,
    className,
    children,
}: {
    id: string;
    label: string;
    required?: boolean;
    hint?: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={['grid gap-1.5', className].filter(Boolean).join(' ')}>
            <Label htmlFor={id} className="text-sm font-bold">
                {label}
                {required && <Required />}
            </Label>
            {children}
            {error ? (
                <FieldError message={error} />
            ) : (
                hint && <p className="text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}

function Required() {
    return (
        <span className="text-destructive" aria-hidden>
            *
        </span>
    );
}

export function FieldError({ message }: { message?: string }) {
    return message ? (
        <p className="text-xs text-destructive">{message}</p>
    ) : null;
}

/**
 * The EMP popup's frame (2b): a tall sheet on phones, a centred panel from
 * `sm` up. The dashboard uses it as is, in the current theme.
 */
export const POPUP =
    'bg-background text-foreground max-sm:top-auto max-sm:bottom-0 max-sm:max-h-[92dvh] max-sm:w-full max-sm:translate-y-0 max-sm:rounded-t-[22px] max-sm:rounded-b-none sm:max-w-md';

/** The popup on guest pages: pins the dark EMP palette whatever the invitation looks like. */
export const NOCTURNE_POPUP = `nocturne font-[Inter,ui-sans-serif,system-ui,sans-serif] ${POPUP}`;

/** A row of the "Who's coming?" card with − / + buttons. */
export function CounterRow({
    label,
    hint,
    value,
    max,
    onChange,
}: {
    label: string;
    hint?: string;
    value: number;
    max: number;
    onChange: (value: number) => void;
}) {
    return (
        <div className="flex items-center border-b border-border px-3.5 py-3 last:border-b-0">
            <div className="flex-1">
                <div className="text-sm font-semibold">{label}</div>
                {hint && <div className="text-xs text-subtle">{hint}</div>}
            </div>
            <div className="flex items-center gap-3.5">
                <button
                    type="button"
                    aria-label={`Fewer ${label.toLowerCase()}`}
                    disabled={value <= 0}
                    onClick={() => onChange(value - 1)}
                    className="flex size-9 items-center justify-center rounded-full border border-input disabled:opacity-40"
                >
                    <Minus className="size-4" />
                </button>
                <span
                    className="w-4 text-center text-base font-bold tabular-nums"
                    aria-live="polite"
                >
                    {value}
                </span>
                <button
                    type="button"
                    aria-label={`More ${label.toLowerCase()}`}
                    disabled={value >= max}
                    onClick={() => onChange(value + 1)}
                    className="flex size-9 items-center justify-center rounded-full border border-input disabled:opacity-40"
                >
                    <Plus className="size-4" />
                </button>
            </div>
        </div>
    );
}

/** A pill that works as a checkbox or radio (dietary options, choices). */
function ChoiceChip({
    type,
    name,
    label,
    checked,
    onChange,
}: {
    type: 'checkbox' | 'radio';
    name?: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label
            className={cn(
                'inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-full px-3.5 text-[13px] font-semibold has-focus-visible:ring-3 has-focus-visible:ring-ring/50',
                checked
                    ? 'bg-strong text-strong-foreground'
                    : 'border border-input bg-card',
            )}
        >
            <input
                type={type}
                name={name}
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                className="sr-only"
            />
            {checked && <Check className="size-3.5" />}
            {label}
        </label>
    );
}
