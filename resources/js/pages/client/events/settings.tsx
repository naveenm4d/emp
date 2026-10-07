import { Head, useForm } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    AlignLeft,
    ArrowDown,
    ArrowUp,
    BriefcaseBusiness,
    Building2,
    Check,
    ChevronDownSquare,
    CircleDot,
    Contact,
    Eye,
    Hash,
    ListChecks,
    LockKeyhole,
    Mail,
    MapPin,
    MessageSquarePlus,
    Minus,
    Phone,
    Plus,
    ToggleLeft,
    Trash2,
    Type,
    Users,
    Utensils,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import { ConfirmBar, useConfirm } from '@/components/shared/confirm-bar';
import { FormField } from '@/components/shared/form-field';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { TimePicker } from '@/components/ui/time-picker';
import { Input } from '@/components/ui/input';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { Select } from '@/components/ui/select';
import { RegistrationDialog } from '@/components/web/invitation/registration-dialog';
import { rsvpSubtitle } from '@/components/web/invitation/rsvp-actions';
import EventLayout from '@/layouts/event-layout';
import { cn } from '@/lib/utils';
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
        max_children: number;
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
 * The event's RSVP form tab: what guests are asked when they register
 * through the public link or RSVP through their personal link, built as
 * sections of switches beside a live preview of the form. The form opens as
 * a popup from the invitation's RSVP button.
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
        form.put(update.url(event), {
            preserveScroll: true,
            // What was saved is the new baseline, so the save bar hides.
            onSuccess: () => form.setDefaults(),
        });
    };

    // What guests will see, from the unsaved changes.
    const preview: RegistrationForm = {
        contact: data.contact,
        allow_maybe: data.attendance.allow_maybe,
        plus_ones: data.party.plus_ones,
        max_additional_guests: data.party.max_additional_guests,
        children: data.party.children,
        max_children: data.party.max_children,
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

    const answered = (question: QuestionDraft) =>
        question.id !== null && answeredQuestionIds.includes(question.id);

    return (
        <EventLayout event={event}>
            <Head title={`RSVP form · ${event.title}`} />
            <PageHeader
                eyebrow={event.title}
                title="RSVP form"
                description="Choose what guests are asked when they register or RSVP."
                actions={
                    <Button
                        type="button"
                        variant="outline"
                        // From lg up the real form is shown beside the settings.
                        className="rounded-full lg:hidden"
                        onClick={() => setPreviewing(true)}
                    >
                        <Eye className="size-4" />
                        Try the form
                    </Button>
                }
            />

            {cancelled && (
                <p className="mb-4 rounded-2xl bg-destructive-muted px-4 py-3 text-sm">
                    This event was cancelled, so its form can no longer be
                    changed.
                </p>
            )}

            <div className="grid grid-cols-1 items-start gap-6 pb-24 *:min-w-0 lg:grid-cols-[minmax(0,1fr)_24rem]">
                <form onSubmit={submit} className="min-w-0">
                    <fieldset
                        disabled={cancelled}
                        className="grid min-w-0 gap-5"
                    >
                        <Section
                            icon={Contact}
                            title="Guest details"
                            description={
                                publicRegistration
                                    ? 'Name is always asked, with an email or a phone so you can reach them.'
                                    : 'Name comes from your guest list. Details you entered are filled in; guests can correct them.'
                            }
                        >
                            <div className="divide-y divide-border">
                                <div className="flex items-center justify-between gap-3 py-3 first:pt-0">
                                    <span className="text-sm font-semibold">
                                        Name
                                    </span>
                                    <span className="rounded-full bg-foreground/6 px-3 py-1 text-xs font-semibold text-muted-foreground">
                                        Always asked
                                    </span>
                                </div>
                                {contactFields.map((field) => {
                                    const Icon = contactIcons[field.key];

                                    return (
                                        <div
                                            key={field.key}
                                            className="flex flex-wrap items-center justify-between gap-3 py-3 last:pb-0"
                                        >
                                            <span className="flex items-center gap-2.5 text-sm font-semibold">
                                                <Icon className="size-4 text-muted-foreground" />
                                                {field.label}
                                            </span>
                                            <SegmentedControl
                                                label={field.label}
                                                className="w-full sm:w-64"
                                                value={data.contact[field.key]}
                                                onChange={(value) =>
                                                    setSettings('contact', {
                                                        [field.key]: value,
                                                    })
                                                }
                                                items={fieldRequirements.map(
                                                    (option) => ({
                                                        value: option.value as FieldRequirement,
                                                        label:
                                                            option.value ===
                                                            'off'
                                                                ? 'Don’t ask'
                                                                : option.label,
                                                    }),
                                                )}
                                            />
                                            <FieldError
                                                message={
                                                    errors[
                                                        `settings.contact.${field.key}`
                                                    ]
                                                }
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                        </Section>

                        <Section
                            icon={Users}
                            title="Attendance & party"
                            description="How guests answer, and who they can bring."
                        >
                            <div className="divide-y divide-border">
                                <ToggleRow
                                    label="Allow “Maybe”"
                                    hint="Besides Attending and Can’t make it."
                                    checked={data.attendance.allow_maybe}
                                    onChange={(allow_maybe) =>
                                        setSettings('attendance', {
                                            allow_maybe,
                                        })
                                    }
                                />
                                <ToggleRow
                                    label="Plus-ones"
                                    hint="Guests say how many people come with them."
                                    checked={data.party.plus_ones}
                                    onChange={(plus_ones) =>
                                        setSettings('party', { plus_ones })
                                    }
                                >
                                    {data.party.plus_ones && (
                                        <div className="mt-3 flex items-center justify-between gap-3 rounded-xl bg-raised px-3.5 py-2.5">
                                            <span className="text-sm text-muted-foreground">
                                                Up to, per guest
                                            </span>
                                            <Stepper
                                                label="Most plus-ones per guest"
                                                value={
                                                    data.party
                                                        .max_additional_guests
                                                }
                                                min={1}
                                                max={20}
                                                onChange={(
                                                    max_additional_guests,
                                                ) =>
                                                    setSettings('party', {
                                                        max_additional_guests,
                                                    })
                                                }
                                            />
                                        </div>
                                    )}
                                    <FieldError
                                        message={
                                            errors[
                                                'settings.party.max_additional_guests'
                                            ]
                                        }
                                    />
                                </ToggleRow>
                                <ToggleRow
                                    label="Children"
                                    hint="Guests say how many children come."
                                    checked={data.party.children}
                                    onChange={(children) =>
                                        setSettings('party', { children })
                                    }
                                >
                                    {data.party.children && (
                                        <div className="mt-3 flex items-center justify-between gap-3 rounded-xl bg-raised px-3.5 py-2.5">
                                            <span className="text-sm text-muted-foreground">
                                                Up to, per guest
                                            </span>
                                            <Stepper
                                                label="Most children per guest"
                                                value={data.party.max_children}
                                                min={1}
                                                max={20}
                                                onChange={(max_children) =>
                                                    setSettings('party', {
                                                        max_children,
                                                    })
                                                }
                                            />
                                        </div>
                                    )}
                                    <FieldError
                                        message={
                                            errors[
                                                'settings.party.max_children'
                                            ]
                                        }
                                    />
                                </ToggleRow>
                            </div>
                        </Section>

                        <Section
                            icon={Utensils}
                            title="Food & dietary"
                            description="Help the caterer plan."
                        >
                            <div className="divide-y divide-border">
                                <ToggleRow
                                    label="Ask about dietary needs"
                                    hint="Guests pick from the options you choose."
                                    checked={data.dietary.enabled}
                                    onChange={(enabled) =>
                                        setSettings('dietary', {
                                            enabled,
                                            options:
                                                enabled &&
                                                data.dietary.options.length ===
                                                    0
                                                    ? dietaryOptions.map(
                                                          (option) =>
                                                              option.value,
                                                      )
                                                    : data.dietary.options,
                                        })
                                    }
                                >
                                    {data.dietary.enabled && (
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {dietaryOptions.map((option) => {
                                                const on =
                                                    data.dietary.options.includes(
                                                        option.value,
                                                    );

                                                return (
                                                    <button
                                                        key={option.value}
                                                        type="button"
                                                        aria-pressed={on}
                                                        onClick={() =>
                                                            setSettings(
                                                                'dietary',
                                                                {
                                                                    options: on
                                                                        ? data.dietary.options.filter(
                                                                              (
                                                                                  value,
                                                                              ) =>
                                                                                  value !==
                                                                                  option.value,
                                                                          )
                                                                        : [
                                                                              ...data
                                                                                  .dietary
                                                                                  .options,
                                                                              option.value,
                                                                          ],
                                                                },
                                                            )
                                                        }
                                                        className={cn(
                                                            'inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-colors',
                                                            on
                                                                ? 'border-transparent bg-strong text-strong-foreground'
                                                                : 'border-input text-muted-foreground hover:bg-raised',
                                                        )}
                                                    >
                                                        {on && (
                                                            <Check className="size-3.5" />
                                                        )}
                                                        {option.label}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    )}
                                    <FieldError
                                        message={
                                            errors['settings.dietary.options']
                                        }
                                    />
                                </ToggleRow>
                                {data.dietary.enabled && (
                                    <ToggleRow
                                        label="Dietary notes"
                                        hint="A free-text box for anything else."
                                        checked={data.dietary.notes}
                                        onChange={(notes) =>
                                            setSettings('dietary', { notes })
                                        }
                                    />
                                )}
                            </div>
                        </Section>

                        <Section
                            icon={MessageSquarePlus}
                            title="Your questions"
                            description="Anything else, like a T-shirt size or which session they’ll join."
                            aside={
                                form.data.questions.length > 0 && (
                                    <span className="rounded-full bg-foreground/6 px-2.5 py-0.5 text-xs font-semibold text-muted-foreground">
                                        {form.data.questions.length}
                                    </span>
                                )
                            }
                        >
                            <div className="grid gap-3">
                                {form.data.questions.map((question, index) => (
                                    <QuestionEditor
                                        key={question.key}
                                        question={question}
                                        questionTypes={questionTypes}
                                        answered={answered(question)}
                                        errors={errors}
                                        index={index}
                                        isFirst={index === 0}
                                        isLast={
                                            index ===
                                            form.data.questions.length - 1
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
                                <button
                                    type="button"
                                    onClick={addQuestion}
                                    className="flex h-14 items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-input text-sm font-semibold text-muted-foreground transition-colors hover:border-primary hover:text-foreground"
                                >
                                    <Plus className="size-4" /> Add a question
                                </button>
                            </div>
                        </Section>

                        <Section
                            icon={LockKeyhole}
                            title="Changing answers"
                            description="Whether a reply is final."
                        >
                            <ToggleRow
                                label="Guests can change their answer"
                                hint="Otherwise their first answer is final."
                                checked={data.responses.editable}
                                onChange={(editable) =>
                                    setSettings('responses', { editable })
                                }
                                first
                            >
                                {data.responses.editable && (
                                    <div className="mt-3 max-w-xs">
                                        <FormField
                                            label="Answers become final at"
                                            htmlFor="responses_lock_at"
                                            error={errors.responses_lock_at}
                                            hint="Leave empty to allow changes until the event starts."
                                        >
                                            <div className="grid grid-cols-[minmax(0,1fr)_9rem] gap-2">
                                                <DatePicker
                                                    id="responses_lock_at"
                                                    value={form.data.responses_lock_at.slice(
                                                        0,
                                                        10,
                                                    )}
                                                    placeholder="Until the event starts"
                                                    clearable
                                                    onChange={(date) =>
                                                        form.setData(
                                                            'responses_lock_at',
                                                            date
                                                                ? `${date}T${form.data.responses_lock_at.slice(11, 16) || '23:59'}`
                                                                : '',
                                                        )
                                                    }
                                                />
                                                <TimePicker
                                                    value={form.data.responses_lock_at.slice(
                                                        11,
                                                        16,
                                                    )}
                                                    placeholder="Time"
                                                    disabled={
                                                        !form.data
                                                            .responses_lock_at
                                                    }
                                                    onChange={(time) =>
                                                        form.setData(
                                                            'responses_lock_at',
                                                            `${form.data.responses_lock_at.slice(0, 10)}T${time || '23:59'}`,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </FormField>
                                    </div>
                                )}
                            </ToggleRow>
                        </Section>
                    </fieldset>

                    {!cancelled && (
                        <div
                            className={cn(
                                'fixed inset-x-4 bottom-24 z-30 mx-auto flex max-w-md items-center gap-3 rounded-full bg-hero py-2 pr-2 pl-5 text-hero-foreground shadow-2xl ring-1 ring-hero-edge transition-all duration-300 md:bottom-6',
                                form.isDirty || form.processing
                                    ? 'translate-y-0 opacity-100'
                                    : 'pointer-events-none translate-y-4 opacity-0',
                            )}
                        >
                            <span className="flex-1 text-sm font-semibold">
                                {form.processing
                                    ? 'Saving…'
                                    : 'You have unsaved changes'}
                            </span>
                            <button
                                type="button"
                                onClick={() => form.reset()}
                                disabled={form.processing}
                                className="h-9 rounded-full px-3 text-sm font-semibold opacity-80 hover:opacity-100"
                            >
                                Discard
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="h-9 rounded-full bg-hero-accent px-5 text-sm font-bold text-hero"
                            >
                                Save
                            </button>
                        </div>
                    )}
                </form>

                <aside className="hidden lg:sticky lg:top-[4.5rem] lg:block">
                    <p className="mb-2.5 flex items-center gap-2 px-1 text-xs font-semibold text-muted-foreground">
                        <Eye className="size-3.5" /> What guests see · live
                        preview
                    </p>
                    <RegistrationDialog
                        inline
                        open
                        onOpenChange={() => {}}
                        mode={publicRegistration ? 'register' : 'rsvp'}
                        form={preview}
                        url={null}
                        subtitle={rsvpSubtitle(event)}
                        note="Preview of what guests fill in."
                        initialAttendance="accepted"
                        className="max-h-[calc(100dvh-8rem)] rounded-3xl shadow-2xl ring-1 ring-border"
                    />
                </aside>
            </div>

            <RegistrationDialog
                // Re-mount so the preview shows the latest changes.
                key={previewing ? 'open' : 'closed'}
                open={previewing}
                onOpenChange={setPreviewing}
                mode={publicRegistration ? 'register' : 'rsvp'}
                form={preview}
                url={null}
                subtitle={rsvpSubtitle(event)}
                note="Preview of what guests fill in."
            />
        </EventLayout>
    );
}

const contactIcons: Record<ContactField, LucideIcon> = {
    email: Mail,
    phone: Phone,
    address: MapPin,
    company: Building2,
    job_title: BriefcaseBusiness,
};

const questionIcons: Record<QuestionType, LucideIcon> = {
    text: Type,
    textarea: AlignLeft,
    number: Hash,
    select: ChevronDownSquare,
    multi_select: ListChecks,
    radio: CircleDot,
    checkbox: ToggleLeft,
} as Record<QuestionType, LucideIcon>;

/** A card of the builder: icon tile, title and what it's for. */
function Section({
    icon: Icon,
    title,
    description,
    aside,
    children,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    aside?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="min-w-0 rounded-3xl bg-card p-4 shadow-card sm:p-5 lg:p-6">
            <div className="mb-4 flex items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                    <Icon className="size-5" strokeWidth={1.9} />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="flex items-center gap-2 text-base font-bold">
                        {title}
                        {aside}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                </div>
            </div>
            {children}
        </section>
    );
}

/** A setting with a switch; `children` show under it (like the plus-ones limit). */
function ToggleRow({
    label,
    hint,
    checked,
    onChange,
    first,
    children,
}: {
    label: string;
    hint?: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    first?: boolean;
    children?: ReactNode;
}) {
    return (
        <div className={cn('py-3 first:pt-0 last:pb-0', first && 'pt-0')}>
            <label className="flex cursor-pointer items-center justify-between gap-4">
                <span className="min-w-0">
                    <span className="block text-sm font-semibold">{label}</span>
                    {hint && (
                        <span className="block text-xs text-muted-foreground">
                            {hint}
                        </span>
                    )}
                </span>
                <Switch checked={checked} onChange={onChange} />
            </label>
            {children}
        </div>
    );
}

function Switch({
    checked,
    onChange,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <span className="relative inline-flex shrink-0">
            <input
                type="checkbox"
                role="switch"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                className="peer sr-only"
            />
            <span className="h-6.5 w-11 rounded-full bg-foreground/14 transition-colors peer-checked:bg-primary peer-focus-visible:ring-3 peer-focus-visible:ring-ring/50 peer-disabled:opacity-50" />
            <span className="absolute top-0.75 left-0.75 size-5 rounded-full bg-card shadow-sm transition-transform peer-checked:translate-x-4.5" />
        </span>
    );
}

/** − value + */
function Stepper({
    label,
    value,
    min,
    max,
    onChange,
}: {
    label: string;
    value: number;
    min: number;
    max: number;
    onChange: (value: number) => void;
}) {
    const button =
        'flex size-8 items-center justify-center rounded-full bg-card shadow-card disabled:opacity-40';

    return (
        <span className="flex items-center gap-3" aria-label={label}>
            <button
                type="button"
                aria-label="Fewer"
                className={button}
                disabled={value <= min}
                onClick={() => onChange(Math.max(min, value - 1))}
            >
                <Minus className="size-4" />
            </button>
            <span className="w-6 text-center text-base font-bold tabular-nums">
                {value}
            </span>
            <button
                type="button"
                aria-label="More"
                className={button}
                disabled={value >= max}
                onClick={() => onChange(Math.min(max, value + 1))}
            >
                <Plus className="size-4" />
            </button>
        </span>
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
    const confirmation = useConfirm();
    const hasOptions = OPTION_TYPES.includes(question.type);
    const options = question.options ?? [];
    const optionError = Object.entries(errors).find(([key]) =>
        key.startsWith(`questions.${index}.options`),
    )?.[1];
    const TypeIcon = questionIcons[question.type] ?? Type;
    const iconButton =
        'flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-card hover:text-foreground disabled:opacity-30';

    return (
        // Phones: number, type and actions on one row, then the question, the
        // choices and Required, each full width. From `sm` up: number,
        // question and actions, then type with Required on the right, then
        // the choices, indented under the question.
        <div className="relative grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-2 gap-y-3 rounded-2xl bg-raised p-3 [grid-template-areas:'num_type_act'_'q_q_q'_'opts_opts_opts'_'req_req_req'] motion-safe:animate-fade-in sm:grid-cols-[auto_12rem_minmax(0,1fr)_auto] sm:gap-x-3 sm:p-4 sm:[grid-template-areas:'num_q_q_act'_'pad_type_req_req'_'pad_opts_opts_opts']">
            <ConfirmBar
                request={confirmation.request}
                onCancel={confirmation.cancel}
            />

            <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-card text-xs font-bold shadow-card [grid-area:num] sm:mt-1.5 sm:self-start">
                {index + 1}
            </span>
            <label className="relative flex min-w-0 items-center [grid-area:type]">
                <TypeIcon className="pointer-events-none absolute left-3 size-4 text-muted-foreground" />
                <Select
                    aria-label="Answer type"
                    className="h-10 w-full bg-card pl-9"
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
            </label>
            <div className="flex shrink-0 items-center [grid-area:act] sm:self-start">
                <button
                    type="button"
                    aria-label="Move up"
                    title="Move up"
                    disabled={isFirst}
                    onClick={() => onMove(-1)}
                    className={iconButton}
                >
                    <ArrowUp className="size-4" />
                </button>
                <button
                    type="button"
                    aria-label="Move down"
                    title="Move down"
                    disabled={isLast}
                    onClick={() => onMove(1)}
                    className={iconButton}
                >
                    <ArrowDown className="size-4" />
                </button>
                <button
                    type="button"
                    aria-label="Remove question"
                    title="Remove question"
                    onClick={() =>
                        answered
                            ? confirmation.ask({
                                  title: 'Guests answered this. Remove it and their answers when you save?',
                                  confirmLabel: 'Remove',
                                  onConfirm: onRemove,
                              })
                            : onRemove()
                    }
                    className={cn(iconButton, 'hover:text-destructive')}
                >
                    <Trash2 className="size-4" />
                </button>
            </div>

            <div className="min-w-0 [grid-area:q]">
                <Input
                    aria-label="Question"
                    placeholder="e.g. What is your T-shirt size?"
                    className="h-11 bg-card text-[15px] font-semibold sm:h-10"
                    value={question.label}
                    onChange={(e) => onChange({ label: e.target.value })}
                />
                <FieldError message={errors[`questions.${index}.label`]} />
            </div>

            {hasOptions && (
                <div className="grid min-w-0 gap-2 [grid-area:opts]">
                    {options.map((option, i) => (
                        <div
                            key={i}
                            className="flex min-w-0 items-center gap-2"
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'size-4 shrink-0 border-2 border-input',
                                    question.type === 'multi_select'
                                        ? 'rounded'
                                        : 'rounded-full',
                                )}
                            />
                            <Input
                                aria-label={`Choice ${i + 1}`}
                                placeholder={`Choice ${i + 1}`}
                                className="h-9 min-w-0 flex-1 bg-card"
                                value={option}
                                onChange={(e) =>
                                    onChange({
                                        options: options.map((value, j) =>
                                            j === i ? e.target.value : value,
                                        ),
                                    })
                                }
                            />
                            <button
                                type="button"
                                aria-label={`Remove choice ${i + 1}`}
                                onClick={() =>
                                    onChange({
                                        options: options.filter(
                                            (_, j) => j !== i,
                                        ),
                                    })
                                }
                                className={cn(iconButton, 'shrink-0')}
                            >
                                <X className="size-4" />
                            </button>
                        </div>
                    ))}
                    <FieldError message={optionError} />
                    <button
                        type="button"
                        onClick={() => onChange({ options: [...options, ''] })}
                        className="inline-flex w-fit items-center gap-1.5 rounded-full px-2 py-1 text-xs font-semibold text-link hover:bg-card"
                    >
                        <Plus className="size-3.5" /> Add choice
                    </button>
                </div>
            )}

            <label className="flex min-w-0 cursor-pointer items-center justify-between gap-3 border-t border-border pt-3 text-sm font-semibold [grid-area:req] sm:justify-end sm:border-0 sm:pt-0">
                <span className="min-w-0">
                    Required
                    {question.type === 'checkbox' && question.required && (
                        <span className="block text-xs font-normal text-muted-foreground">
                            Guests must tick it to send their answer.
                        </span>
                    )}
                </span>
                <Switch
                    checked={question.required}
                    onChange={(required) => onChange({ required })}
                />
            </label>
        </div>
    );
}

function FieldError({ message }: { message?: string }) {
    return message ? (
        <p className="mt-1 text-xs text-destructive">{message}</p>
    ) : null;
}
