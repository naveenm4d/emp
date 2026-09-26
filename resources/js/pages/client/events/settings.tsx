import { Head, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Eye, Plus, Trash2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { EventTabs } from '@/components/events/event-tabs';
import { FormField } from '@/components/shared/form-field';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { RegistrationDialog } from '@/components/web/invitation/registration-dialog';
import ClientLayout from '@/layouts/client-layout';
import { update } from '@/routes/client/events/settings';
import type { Event, Option, Resource } from '@/types';
import type {
    ContactField,
    FieldRequirement,
    QuestionType,
    RegistrationForm,
} from '@/types/web';

/** RegistrationSettings::toArray() */
type Settings = {
    contact: Record<ContactField, FieldRequirement>;
    attendance: { allow_maybe: boolean };
    party: {
        plus_ones: boolean;
        max_additional_guests: number;
        children: boolean;
    };
    dietary: { enabled: boolean; options: string[]; notes: boolean };
    responses: { editable: boolean };
};

type Question = {
    id: string | null;
    label: string;
    type: QuestionType;
    required: boolean;
    options: string[] | null;
};

/** A question being edited; `key` keeps React rows stable before it has an id. */
type QuestionDraft = Question & { key: string };

type FormData = {
    settings: Settings;
    responses_lock_at: string;
    questions: QuestionDraft[];
};

type Props = {
    event: Resource<Event>;
    settings: Settings;
    responsesLockAt: string | null;
    questions: Question[];
    answeredQuestionIds: string[];
    questionTypes: Option[];
    dietaryOptions: Option[];
    fieldRequirements: Option[];
};

const OPTION_TYPES: QuestionType[] = ['select', 'multi_select', 'radio'];

const contactFields: { key: ContactField; label: string }[] = [
    { key: 'email', label: 'Email' },
    { key: 'phone', label: 'Phone' },
    { key: 'address', label: 'Address' },
    { key: 'company', label: 'Company' },
    { key: 'job_title', label: 'Job title' },
];

/**
 * The event's Settings tab: what guests are asked when they register
 * through the public link or RSVP through their personal link. The form
 * opens as a popup from the invitation's RSVP section.
 */
export default function EventSettingsPage({
    event: { data: event },
    settings,
    responsesLockAt,
    questions,
    answeredQuestionIds,
    questionTypes,
    dietaryOptions,
    fieldRequirements,
}: Props) {
    const nextKey = useRef(0);
    const newKey = () => `new-${nextKey.current++}`;
    const [previewing, setPreviewing] = useState(false);

    const form = useForm<FormData>({
        settings,
        responses_lock_at: responsesLockAt ?? '',
        questions: questions.map((question) => ({
            ...question,
            key: question.id ?? newKey(),
        })),
    });

    const publicRegistration = event.registration_type !== 'guest_list_only';
    const cancelled = event.state === 'cancelled';
    const errors = form.errors as Record<string, string | undefined>;
    const { settings: data } = form.data;

    const setSettings = <K extends keyof Settings>(
        section: K,
        values: Partial<Settings[K]>,
    ) =>
        form.setData('settings', {
            ...data,
            [section]: { ...data[section], ...values },
        });

    const setQuestion = (index: number, values: Partial<QuestionDraft>) =>
        form.setData(
            'questions',
            form.data.questions.map((question, i) =>
                i === index ? { ...question, ...values } : question,
            ),
        );

    const moveQuestion = (index: number, by: -1 | 1) => {
        const list = [...form.data.questions];
        [list[index], list[index + by]] = [list[index + by], list[index]];
        form.setData('questions', list);
    };

    const addQuestion = () =>
        form.setData('questions', [
            ...form.data.questions,
            {
                key: newKey(),
                id: null,
                label: '',
                type: 'text',
                required: false,
                options: null,
            },
        ]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((values) => ({
            ...values,
            responses_lock_at: values.responses_lock_at || null,
            questions: values.questions.map((question) => ({
                id: question.id,
                label: question.label,
                type: question.type,
                required: question.required,
                options: OPTION_TYPES.includes(question.type)
                    ? (question.options ?? [])
                    : null,
            })),
        }));
        form.put(update.url(event), { preserveScroll: true });
    };

    // What guests will see, from the unsaved changes.
    const preview: RegistrationForm = {
        contact: data.contact,
        allow_maybe: data.attendance.allow_maybe,
        plus_ones: data.party.plus_ones,
        max_additional_guests: data.party.max_additional_guests,
        children: data.party.children,
        max_children: 20,
        dietary_options: data.dietary.enabled
            ? dietaryOptions.filter((option) =>
                  data.dietary.options.includes(option.value),
              )
            : [],
        dietary_notes: data.dietary.enabled && data.dietary.notes,
        questions: form.data.questions
            .filter((question) => question.label.trim() !== '')
            .map((question) => ({
                ...question,
                id: question.key,
                options: OPTION_TYPES.includes(question.type)
                    ? (question.options ?? []).filter(Boolean)
                    : null,
            })),
    };

    return (
        <ClientLayout>
            <Head title={`Settings · ${event.title}`} />
            <PageHeader
                title={event.title}
                description="Choose what guests are asked when they register or RSVP."
                actions={
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setPreviewing(true)}
                    >
                        <Eye className="size-4" />
                        Preview form
                    </Button>
                }
            />
            <EventTabs event={event} />

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3">
                <fieldset
                    disabled={cancelled}
                    className="grid min-w-0 gap-6 lg:col-span-2"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Guest information</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <SettingRow
                                label="Name"
                                hint={
                                    publicRegistration
                                        ? 'Always asked, together with an email or a phone.'
                                        : 'Already on your guest list.'
                                }
                            >
                                <span className="text-sm text-muted-foreground">
                                    Required
                                </span>
                            </SettingRow>
                            {contactFields.map((field) => (
                                <SettingRow
                                    key={field.key}
                                    label={field.label}
                                    hint={
                                        ['email', 'phone'].includes(field.key)
                                            ? publicRegistration
                                                ? 'People who register leave an email or a phone, so you can reach them.'
                                                : 'Filled in with what you entered; guests can check and correct it.'
                                            : undefined
                                    }
                                    error={
                                        errors[`settings.contact.${field.key}`]
                                    }
                                >
                                    <Select
                                        aria-label={field.label}
                                        className="w-36"
                                        value={data.contact[field.key]}
                                        onChange={(e) =>
                                            setSettings('contact', {
                                                [field.key]: e.target
                                                    .value as FieldRequirement,
                                            })
                                        }
                                    >
                                        {fieldRequirements.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.value === 'off'
                                                    ? "Don't ask"
                                                    : option.label}
                                            </option>
                                        ))}
                                    </Select>
                                </SettingRow>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Attendance &amp; party</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <Checkbox
                                checked={data.attendance.allow_maybe}
                                onChange={(allow_maybe) =>
                                    setSettings('attendance', { allow_maybe })
                                }
                                label="Allow “Maybe”"
                                hint="Besides Accept and Decline."
                            />
                            <div className="flex flex-wrap items-center gap-3">
                                <Checkbox
                                    checked={data.party.plus_ones}
                                    onChange={(plus_ones) =>
                                        setSettings('party', { plus_ones })
                                    }
                                    label="Plus-ones"
                                    hint="Guests say how many people come with them."
                                />
                                {data.party.plus_ones && (
                                    <label className="flex items-center gap-2 text-sm">
                                        Up to
                                        <Input
                                            type="number"
                                            min={1}
                                            max={20}
                                            className="w-20"
                                            value={
                                                data.party.max_additional_guests
                                            }
                                            onChange={(e) =>
                                                setSettings('party', {
                                                    max_additional_guests:
                                                        Number(e.target.value),
                                                })
                                            }
                                        />
                                        per guest
                                    </label>
                                )}
                            </div>
                            <FieldError
                                message={
                                    errors[
                                        'settings.party.max_additional_guests'
                                    ]
                                }
                            />
                            <Checkbox
                                checked={data.party.children}
                                onChange={(children) =>
                                    setSettings('party', { children })
                                }
                                label="Children"
                                hint="Guests say how many children come."
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Food &amp; dietary</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <Checkbox
                                checked={data.dietary.enabled}
                                onChange={(enabled) =>
                                    setSettings('dietary', {
                                        enabled,
                                        options:
                                            enabled &&
                                            data.dietary.options.length === 0
                                                ? dietaryOptions.map(
                                                      (option) => option.value,
                                                  )
                                                : data.dietary.options,
                                    })
                                }
                                label="Ask about dietary restrictions"
                            />
                            {data.dietary.enabled && (
                                <>
                                    <div className="flex flex-wrap gap-x-4 gap-y-2 pl-6">
                                        {dietaryOptions.map((option) => (
                                            <label
                                                key={option.value}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={data.dietary.options.includes(
                                                        option.value,
                                                    )}
                                                    onChange={(e) =>
                                                        setSettings('dietary', {
                                                            options: e.target
                                                                .checked
                                                                ? [
                                                                      ...data
                                                                          .dietary
                                                                          .options,
                                                                      option.value,
                                                                  ]
                                                                : data.dietary.options.filter(
                                                                      (value) =>
                                                                          value !==
                                                                          option.value,
                                                                  ),
                                                        })
                                                    }
                                                />
                                                {option.label}
                                            </label>
                                        ))}
                                    </div>
                                    <FieldError
                                        message={
                                            errors['settings.dietary.options']
                                        }
                                    />
                                    <div className="pl-6">
                                        <Checkbox
                                            checked={data.dietary.notes}
                                            onChange={(notes) =>
                                                setSettings('dietary', {
                                                    notes,
                                                })
                                            }
                                            label="Dietary notes"
                                            hint="A free-text box for anything else."
                                        />
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Custom questions</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {form.data.questions.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Ask anything else, like a T-shirt size or
                                    which workshop guests will attend.
                                </p>
                            )}
                            {form.data.questions.map((question, index) => (
                                <QuestionEditor
                                    key={question.key}
                                    question={question}
                                    questionTypes={questionTypes}
                                    answered={
                                        question.id !== null &&
                                        answeredQuestionIds.includes(
                                            question.id,
                                        )
                                    }
                                    errors={errors}
                                    index={index}
                                    isFirst={index === 0}
                                    isLast={
                                        index === form.data.questions.length - 1
                                    }
                                    onChange={(values) =>
                                        setQuestion(index, values)
                                    }
                                    onMove={(by) => moveQuestion(index, by)}
                                    onRemove={() =>
                                        form.setData(
                                            'questions',
                                            form.data.questions.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                />
                            ))}
                            <FieldError message={errors.questions} />
                            <div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addQuestion}
                                >
                                    <Plus className="size-4" />
                                    Add question
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </fieldset>

                <fieldset
                    disabled={cancelled}
                    className="grid content-start gap-6"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Changing answers</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <Checkbox
                                checked={data.responses.editable}
                                onChange={(editable) =>
                                    setSettings('responses', { editable })
                                }
                                label="Guests can change their answer"
                                hint="Otherwise their first answer is final."
                            />
                            {data.responses.editable && (
                                <FormField
                                    label="Answers become final at"
                                    htmlFor="responses_lock_at"
                                    error={errors.responses_lock_at}
                                    hint="Leave empty to allow changes until the event starts."
                                >
                                    <Input
                                        id="responses_lock_at"
                                        type="datetime-local"
                                        value={form.data.responses_lock_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'responses_lock_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            )}
                        </CardContent>
                    </Card>

                    {cancelled ? (
                        <p className="text-sm text-muted-foreground">
                            Cancelled events can no longer be changed.
                        </p>
                    ) : (
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save settings'}
                        </Button>
                    )}
                </fieldset>
            </form>

            <RegistrationDialog
                // Re-mount so the preview shows the latest changes.
                key={previewing ? 'open' : 'closed'}
                open={previewing}
                onOpenChange={setPreviewing}
                mode={publicRegistration ? 'register' : 'rsvp'}
                form={preview}
                url={null}
                title={
                    publicRegistration
                        ? `Join ${event.title}`
                        : "Great, you're coming!"
                }
                description="Preview of what guests fill in."
            />
        </ClientLayout>
    );
}

function QuestionEditor({
    question,
    questionTypes,
    answered,
    errors,
    index,
    isFirst,
    isLast,
    onChange,
    onMove,
    onRemove,
}: {
    question: QuestionDraft;
    questionTypes: Option[];
    answered: boolean;
    errors: Record<string, string | undefined>;
    index: number;
    isFirst: boolean;
    isLast: boolean;
    onChange: (values: Partial<QuestionDraft>) => void;
    onMove: (by: -1 | 1) => void;
    onRemove: () => void;
}) {
    const hasOptions = OPTION_TYPES.includes(question.type);
    const options = question.options ?? [];
    const optionError = Object.entries(errors).find(([key]) =>
        key.startsWith(`questions.${index}.options`),
    )?.[1];

    return (
        <div className="grid gap-3 rounded-lg border border-border p-3">
            <div className="flex flex-wrap items-start gap-2">
                <div className="min-w-48 flex-1">
                    <Input
                        aria-label="Question"
                        placeholder="e.g. What is your T-shirt size?"
                        value={question.label}
                        onChange={(e) => onChange({ label: e.target.value })}
                    />
                    <FieldError message={errors[`questions.${index}.label`]} />
                </div>
                <Select
                    aria-label="Answer type"
                    className="w-40"
                    value={question.type}
                    // Answers only make sense for the type they were given in.
                    disabled={answered}
                    title={
                        answered
                            ? 'Guests already answered, so the type is fixed.'
                            : undefined
                    }
                    onChange={(e) => {
                        const type = e.target.value as QuestionType;
                        onChange({
                            type,
                            options:
                                OPTION_TYPES.includes(type) && !options.length
                                    ? ['', '']
                                    : question.options,
                        });
                    }}
                >
                    {questionTypes.map((type) => (
                        <option key={type.value} value={type.value}>
                            {type.label}
                        </option>
                    ))}
                </Select>
                <div className="flex items-center">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Move up"
                        disabled={isFirst}
                        onClick={() => onMove(-1)}
                    >
                        <ArrowUp className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Move down"
                        disabled={isLast}
                        onClick={() => onMove(1)}
                    >
                        <ArrowDown className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Remove question"
                        onClick={() => {
                            if (
                                !answered ||
                                window.confirm(
                                    'Guests already answered this question. Removing it deletes their answers when you save.',
                                )
                            ) {
                                onRemove();
                            }
                        }}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </div>

            {hasOptions && (
                <div className="grid gap-2 pl-1">
                    {options.map((option, i) => (
                        <div key={i} className="flex items-center gap-2">
                            <Input
                                aria-label={`Choice ${i + 1}`}
                                placeholder={`Choice ${i + 1}`}
                                value={option}
                                onChange={(e) =>
                                    onChange({
                                        options: options.map((value, j) =>
                                            j === i ? e.target.value : value,
                                        ),
                                    })
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={`Remove choice ${i + 1}`}
                                onClick={() =>
                                    onChange({
                                        options: options.filter(
                                            (_, j) => j !== i,
                                        ),
                                    })
                                }
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                    ))}
                    <FieldError message={optionError} />
                    <div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                onChange({ options: [...options, ''] })
                            }
                        >
                            <Plus className="size-4" />
                            Add choice
                        </Button>
                    </div>
                </div>
            )}

            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={question.required}
                    onChange={(e) => onChange({ required: e.target.checked })}
                />
                Required
                {question.type === 'checkbox' && question.required && (
                    <span className="text-muted-foreground">
                        (guests must tick it)
                    </span>
                )}
            </label>
        </div>
    );
}

function SettingRow({
    label,
    hint,
    error,
    children,
}: {
    label: string;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3 last:border-0 last:pb-0">
            <div className="min-w-0">
                <p className="text-sm font-medium">{label}</p>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
                <FieldError message={error} />
            </div>
            {children}
        </div>
    );
}

function Checkbox({
    checked,
    onChange,
    label,
    hint,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: string;
    hint?: string;
}) {
    return (
        <label className="flex items-start gap-2 text-sm">
            <input
                type="checkbox"
                className="mt-0.5"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
            />
            <span>
                <span className="font-medium">{label}</span>
                {hint && (
                    <span className="block text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </span>
        </label>
    );
}

function FieldError({ message }: { message?: string }) {
    return message ? (
        <p className="mt-1 text-xs text-destructive">{message}</p>
    ) : null;
}
