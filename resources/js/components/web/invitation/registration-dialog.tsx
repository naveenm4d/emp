import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    AnswerValue,
    ContactField,
    RegistrationForm,
    RegistrationQuestion,
    RegistrationResponse,
} from '@/types/web';

/**
 * register: public sign-up (name + contact + details).
 * rsvp:     a guest's answer from their personal link (details only).
 * preview:  what guests will see; nothing is sent.
 */
export type RegistrationDialogMode = 'register' | 'rsvp' | 'preview';

type Attendance = 'accepted' | 'maybe';

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
    attendance?: Attendance;
};

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

/** The form asks for more than the guest's attendance. */
export function hasRegistrationDetails(form: RegistrationForm): boolean {
    return (
        Object.values(form.contact).some((field) => field !== 'off') ||
        form.plus_ones ||
        form.children ||
        form.dietary_options.length > 0 ||
        form.dietary_notes ||
        form.questions.length > 0
    );
}

type RegistrationDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: RegistrationDialogMode;
    form: RegistrationForm;
    /** Where to post; null in preview. */
    url: string | null;
    title: string;
    description?: string;
    /** RSVP only: the answer being given. */
    attendance?: Attendance;
    /** RSVP only: what the guest answered before. */
    initial?: RegistrationResponse | null;
    /** Called after the server accepted the form (not for flash errors). */
    onSaved?: () => void;
};

/**
 * The details an event asks for (Settings tab), shown as a popup from the
 * EMP app rather than the template. Portals to <body>, so it sits outside
 * the invitation's shadow root and template styles don't reach it.
 */
export function RegistrationDialog({
    open,
    onOpenChange,
    mode,
    form: config,
    url,
    title,
    description,
    attendance,
    initial,
    onSaved,
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
    });

    const error = (key: string) =>
        form.errors[key as keyof FormData] ??
        Object.entries(form.errors).find(([field]) =>
            field.startsWith(`${key}.`),
        )?.[1];

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (mode === 'preview' || !url) {
            return;
        }

        form.transform((data) => ({
            ...data,
            // "Just me" comes alone, so without children.
            children: justMe ? 0 : data.children,
            ...(mode === 'rsvp' ? { attendance } : {}),
        }));
        form.post(url, {
            preserveScroll: true,
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

    // Children are asked once the guest brings someone (or when there are no plus-ones to pick).
    const justMe = config.plus_ones && form.data.additional_guests === 0;
    const asksChildren = config.children && !justMe;

    const shownContact = contactFields.filter(
        (field) => config.contact[field.key] !== 'off',
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>

                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                    noValidate
                >
                    <div className="grid gap-4 overflow-y-auto p-4">
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

                        {shownContact.length > 0 && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                {shownContact.map((field) => (
                                    <Field
                                        key={field.key}
                                        id={`reg-${field.key}`}
                                        label={field.label}
                                        required={
                                            config.contact[field.key] ===
                                            'required'
                                        }
                                        error={error(field.key)}
                                        className={
                                            field.key === 'address'
                                                ? 'sm:col-span-2'
                                                : undefined
                                        }
                                    >
                                        <Input
                                            id={`reg-${field.key}`}
                                            type={field.type ?? 'text'}
                                            autoComplete={field.autoComplete}
                                            value={form.data[field.key]}
                                            onChange={(e) =>
                                                form.setData(
                                                    field.key,
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                ))}
                            </div>
                        )}

                        {(config.plus_ones || config.children) && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                {config.plus_ones && (
                                    <Field
                                        id="reg-additional"
                                        label="Additional guests"
                                        hint={`Up to ${config.max_additional_guests}`}
                                        error={error('additional_guests')}
                                    >
                                        <Select
                                            id="reg-additional"
                                            value={form.data.additional_guests}
                                            onChange={(e) =>
                                                form.setData(
                                                    'additional_guests',
                                                    Number(e.target.value),
                                                )
                                            }
                                        >
                                            {range(
                                                config.max_additional_guests,
                                            ).map((count) => (
                                                <option
                                                    key={count}
                                                    value={count}
                                                >
                                                    {count === 0
                                                        ? 'Just me'
                                                        : `+${count}`}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                )}
                                {asksChildren && (
                                    <Field
                                        id="reg-children"
                                        label="Children"
                                        error={error('children')}
                                    >
                                        <Input
                                            id="reg-children"
                                            type="number"
                                            min={0}
                                            max={config.max_children}
                                            value={form.data.children}
                                            onChange={(e) =>
                                                form.setData(
                                                    'children',
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                            </div>
                        )}

                        {config.dietary_options.length > 0 && (
                            <fieldset className="grid gap-2">
                                <legend className="mb-2 text-sm font-medium">
                                    Dietary restrictions
                                </legend>
                                <div className="flex flex-wrap gap-x-4 gap-y-2">
                                    {config.dietary_options.map((option) => (
                                        <label
                                            key={option.value}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={form.data.dietary_restrictions.includes(
                                                    option.value,
                                                )}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'dietary_restrictions',
                                                        toggle(
                                                            form.data
                                                                .dietary_restrictions,
                                                            option.value,
                                                            e.target.checked,
                                                        ),
                                                    )
                                                }
                                            />
                                            {option.label}
                                        </label>
                                    ))}
                                </div>
                                <FieldError
                                    message={error('dietary_restrictions')}
                                />
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
                                    value={form.data.dietary_notes}
                                    onChange={(e) =>
                                        form.setData(
                                            'dietary_notes',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                        )}

                        {config.questions.map((question) => (
                            <QuestionField
                                key={question.id}
                                question={question}
                                value={form.data.answers[question.id] ?? null}
                                error={error(`answers.${question.id}`)}
                                onChange={(value) =>
                                    setAnswer(question.id, value)
                                }
                                toggle={toggle}
                            />
                        ))}
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t p-4">
                        {mode === 'preview' && (
                            <p className="mr-auto text-xs text-muted-foreground">
                                Preview: nothing is sent.
                            </p>
                        )}
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={mode === 'preview' || form.processing}
                        >
                            {form.processing
                                ? 'Sending…'
                                : mode === 'register'
                                  ? 'Register'
                                  : 'Send reply'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
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
                <label className="flex items-center gap-2 text-sm font-medium">
                    <input
                        id={id}
                        type="checkbox"
                        checked={value === true}
                        onChange={(e) => onChange(e.target.checked)}
                    />
                    {question.label}
                    {question.required && <Required />}
                </label>
                <FieldError message={error} />
            </div>
        );
    }

    if (question.type === 'radio' || question.type === 'multi_select') {
        const selected = Array.isArray(value) ? value : [];

        return (
            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">
                    {question.label}
                    {question.required && <Required />}
                </legend>
                <div className="flex flex-wrap gap-x-4 gap-y-2">
                    {options.map((option) => (
                        <label
                            key={option}
                            className="flex items-center gap-2 text-sm"
                        >
                            {question.type === 'radio' ? (
                                <input
                                    type="radio"
                                    name={id}
                                    checked={value === option}
                                    onChange={() => onChange(option)}
                                />
                            ) : (
                                <input
                                    type="checkbox"
                                    checked={selected.includes(option)}
                                    onChange={(e) =>
                                        onChange(
                                            toggle(
                                                selected,
                                                option,
                                                e.target.checked,
                                            ),
                                        )
                                    }
                                />
                            )}
                            {option}
                        </label>
                    ))}
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

function Field({
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
            <Label htmlFor={id}>
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

function FieldError({ message }: { message?: string }) {
    return message ? (
        <p className="text-xs text-destructive">{message}</p>
    ) : null;
}

function range(max: number): number[] {
    return Array.from({ length: max + 1 }, (_, index) => index);
}
