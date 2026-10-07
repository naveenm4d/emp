import { useForm } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BellRing,
    CalendarClock,
    CalendarDays,
    DoorOpen,
    Rocket,
    ArrowLeft,
    ArrowRight,
    Check,
    LayoutTemplate,
    MessageCircle,
    Sparkles,
    Users,
} from 'lucide-react';

import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { MapPreview } from '@/components/events/map-preview';
import { MessageField } from '@/components/events/message-field';
import { RegistrationTypePicker } from '@/components/events/registration-type-picker';
import { eventTypeIcons } from '@/components/events/event-pills';
import { Stepper, Switch } from '@/components/shared/form-section';
import { TemplatePicker } from '@/components/templates/template-picker';
import { DatePicker } from '@/components/ui/date-picker';
import { TimePicker } from '@/components/ui/time-picker';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { todayInAppTimeZone } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    Event,
    Option,
    RegistrationType,
    RegistrationTypeOption,
    Template,
} from '@/types';

export type EventFormData = {
    title: string;
    slug: string;
    template_id: string;
    description: string;
    max_capacity: number | '';
    registration_type: RegistrationType;
    event_type: string;
    location_name: string;
    location_address: string;
    map_url: string;
    event_date: string;
    start_time: string;
    end_time: string;
    invitation_message: string;
    reminder_message: string;
    /** Email only (WhatsApp and SMS have no subject). */
    invitation_subject: string;
    reminder_subject: string;
    auto_reminders: boolean;
    remind_after_days: number | '';
    remind_before_days: number | '';
};

type EventFormProps = {
    event?: Event;
    /** Designs to choose from; leave out to edit details only (the design is changed elsewhere). */
    templates?: Template[];
    eventTypes: Option[];
    registrationTypes: RegistrationTypeOption[];
    /** WhatsApp text sent with RSVP links when the event sets none. */
    defaultInvitationMessage: string;
    /** WhatsApp text for reminders when the event sets none. */
    defaultReminderMessage: string;
    /** Email subjects used when the event sets none. */
    defaultInvitationSubject: string;
    defaultReminderSubject: string;
    /** Endpoint that turns a map link into an embeddable map (client or admin area). */
    mapPreviewUrl: (mapUrl: string) => string;
    /** JSON preview endpoint for a template (client or admin area). */
    templatePreviewUrl?: (template: Template) => string;
    /** Preselected design when creating (e.g. chosen on the Templates page). */
    defaultTemplateId?: string | null;
    submitLabel: string;
    onSubmit: (form: ReturnType<typeof useForm<EventFormData>>) => void;
    /** The page's own sections (e.g. status, registration, danger zone), placed among the form's. */
    extraSections?: ExtraSection[];
};

/** A section the page adds to the form's nav and panels. */
export type ExtraSection = {
    id: string;
    title: string;
    description?: string;
    icon: LucideIcon;
    /** Where it goes: first, right after "Who can join", or last. */
    placement: 'start' | 'access' | 'end';
    /** Its rows (use `SettingRow`); the panel chrome comes from the form. */
    content: ReactNode;
    /** Red panel (the danger zone). */
    danger?: boolean;
};

/** Create / edit form for an event. The parent decides which route to hit. */
export function EventForm({
    event,
    templates,
    eventTypes,
    registrationTypes,
    defaultInvitationMessage,
    defaultReminderMessage,
    defaultInvitationSubject,
    defaultReminderSubject,
    mapPreviewUrl,
    templatePreviewUrl,
    defaultTemplateId,
    submitLabel,
    onSubmit,
    extraSections = [],
}: EventFormProps) {
    const choosesTemplate =
        templates !== undefined && templatePreviewUrl !== undefined;

    const form = useForm<EventFormData>({
        title: event?.title ?? '',
        slug: event?.slug ?? '',
        template_id: event?.template?.id ?? defaultTemplateId ?? '',
        description: event?.description ?? '',
        max_capacity: event?.max_capacity ?? 0,
        registration_type: event?.registration_type ?? 'guest_list_only',
        event_type: event?.event_type ?? '',
        location_name: event?.location_name ?? '',
        location_address: event?.location_address ?? '',
        map_url: event?.map_url ?? '',
        event_date: event?.event_date ?? '',
        start_time: event?.start_time ?? '',
        end_time: event?.end_time ?? '',
        invitation_message: event?.invitation_message ?? '',
        reminder_message: event?.reminder_message ?? '',
        invitation_subject: event?.invitation_subject ?? '',
        reminder_subject: event?.reminder_subject ?? '',
        auto_reminders: event?.auto_reminders ?? false,
        remind_after_days: event?.remind_after_days ?? 3,
        remind_before_days: event?.remind_before_days ?? 2,
    });

    const messageSample = {
        'guest.name': 'Anissa',
        'event.title': form.data.title || 'Your event',
        'event.date': form.data.event_date,
        'event.time': form.data.start_time,
        'event.venue': form.data.location_name,
        'rsvp.link': `${window.location.origin}/${form.data.slug || 'your-event'}/k7mq2xvt`,
    };

    const field = (
        key: Exclude<
            keyof EventFormData,
            | 'registration_type'
            | 'template_id'
            | 'event_type'
            | 'auto_reminders'
        >,
    ) => ({
        id: key,
        value: form.data[key],
        'aria-invalid': !!form.errors[key],
        onChange: (
            e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
        ) => form.setData((data) => ({ ...data, [key]: e.target.value })),
    });

    const eventDate = form.data.event_date;
    const extras = (placement: ExtraSection['placement']) =>
        extraSections.filter((section) => section.placement === placement);

    const sections: (ExtraSection & { hasError?: boolean })[] = [
        ...extras('start'),
        ...(choosesTemplate
            ? [
                  {
                      id: 'design',
                      title: 'Invitation design',
                      description:
                          'Guests see your invitation in this design. Photos, videos and music go in on the Design tab.',
                      icon: LayoutTemplate,
                      placement: 'start' as const,
                      hasError: !!form.errors.template_id,
                      content: (
                          <SettingRow stacked>
                              {event?.template &&
                                  form.data.template_id !==
                                      event.template.id && (
                                      <p className="mb-3 rounded-xl bg-warning-muted px-3 py-2 text-xs">
                                          Switching templates keeps your
                                          uploads, but media the new template
                                          has no place for is not shown.
                                      </p>
                                  )}
                              <TemplatePicker
                                  templates={templates ?? []}
                                  value={form.data.template_id}
                                  onChange={(id) =>
                                      form.setData('template_id', id)
                                  }
                                  invalid={!!form.errors.template_id}
                                  previewUrl={templatePreviewUrl!}
                                  previewDetails={{
                                      title: form.data.title,
                                      description: form.data.description,
                                      event_type: form.data.event_type,
                                      event_date: form.data.event_date,
                                      start_time: form.data.start_time,
                                      end_time: form.data.end_time,
                                      location_name: form.data.location_name,
                                      location_address:
                                          form.data.location_address,
                                  }}
                              />
                              <FieldError message={form.errors.template_id} />
                          </SettingRow>
                      ),
                  },
              ]
            : []),
        {
            id: 'details',
            title: 'Details',
            description: 'What the event is called and what it is.',
            icon: Sparkles,
            placement: 'start',
            hasError: !!(
                form.errors.title ||
                form.errors.slug ||
                form.errors.description ||
                form.errors.event_type
            ),
            content: (
                <>
                    <SettingRow
                        label="Title"
                        hint="Shown on the invitation and in messages."
                        htmlFor="title"
                    >
                        <Input
                            {...field('title')}
                            autoFocus={!event}
                            placeholder="e.g. Nimali & Kasun’s Wedding"
                        />
                        <FieldError message={form.errors.title} />
                    </SettingRow>
                    <SettingRow
                        label="Event type"
                        hint="Sets sensible defaults."
                    >
                        <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                            {eventTypes.map((type) => {
                                const Icon =
                                    eventTypeIcons[type.value] ?? CalendarClock;
                                const selected =
                                    form.data.event_type === type.value;

                                return (
                                    <button
                                        key={type.value}
                                        type="button"
                                        aria-pressed={selected}
                                        onClick={() =>
                                            form.setData(
                                                'event_type',
                                                selected ? '' : type.value,
                                            )
                                        }
                                        className={cn(
                                            'flex h-9 min-w-0 items-center gap-2 rounded-lg border px-2.5 text-left text-[13px] font-semibold transition-colors',
                                            selected
                                                ? 'border-transparent bg-strong text-strong-foreground'
                                                : 'border-input text-muted-foreground hover:bg-raised hover:text-foreground',
                                        )}
                                    >
                                        <Icon className="size-3.5 shrink-0" />
                                        <span className="truncate">
                                            {type.label}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                        <FieldError message={form.errors.event_type} />
                    </SettingRow>
                    <SettingRow
                        label="Description"
                        hint="A few words for your guests."
                        htmlFor="description"
                    >
                        <Textarea {...field('description')} rows={4} />
                        <FieldError message={form.errors.description} />
                    </SettingRow>
                    <SettingRow
                        label="Link"
                        hint={
                            event
                                ? 'Links you already shared keep working if you change it.'
                                : 'Leave empty to make one from the title.'
                        }
                        htmlFor="slug"
                    >
                        <div className="flex h-10 min-w-0 items-center overflow-hidden rounded-lg border border-input bg-card focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50">
                            <span className="flex h-full max-w-[45%] shrink-0 items-center truncate border-r border-input bg-raised px-3 text-sm text-muted-foreground">
                                {window.location.host}/
                            </span>
                            <input
                                {...field('slug')}
                                placeholder="john-and-amy"
                                className="h-full min-w-0 flex-1 bg-transparent px-3 text-base outline-none placeholder:text-muted-foreground md:text-sm"
                            />
                        </div>
                        <FieldError message={form.errors.slug} />
                    </SettingRow>
                </>
            ),
        },
        {
            id: 'when',
            title: 'Date & venue',
            description: 'When it happens and where.',
            icon: CalendarDays,
            placement: 'start',
            hasError: !!(
                form.errors.event_date ||
                form.errors.start_time ||
                form.errors.end_time ||
                form.errors.location_name ||
                form.errors.location_address ||
                form.errors.map_url
            ),
            content: (
                <>
                    <SettingRow label="Date" htmlFor="event_date">
                        <DatePicker
                            id="event_date"
                            value={eventDate ?? ''}
                            min={event ? undefined : todayInAppTimeZone()}
                            invalid={!!form.errors.event_date}
                            clearable
                            onChange={(value) =>
                                form.setData((data) => ({
                                    ...data,
                                    event_date: value,
                                }))
                            }
                        />
                        <FieldError message={form.errors.event_date} />
                    </SettingRow>
                    <SettingRow
                        label="Time"
                        hint="The end time is optional."
                        htmlFor="start_time"
                    >
                        <div className="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2">
                            <TimePicker
                                id="start_time"
                                value={form.data.start_time ?? ''}
                                placeholder="Starts"
                                invalid={!!form.errors.start_time}
                                clearable
                                onChange={(value) =>
                                    form.setData((data) => ({
                                        ...data,
                                        start_time: value,
                                    }))
                                }
                            />
                            <span className="text-xs text-muted-foreground">
                                to
                            </span>
                            <TimePicker
                                id="end_time"
                                value={form.data.end_time ?? ''}
                                min={form.data.start_time || undefined}
                                placeholder="Ends"
                                invalid={!!form.errors.end_time}
                                clearable
                                onChange={(value) =>
                                    form.setData((data) => ({
                                        ...data,
                                        end_time: value,
                                    }))
                                }
                            />
                        </div>
                        <FieldError
                            message={
                                form.errors.start_time ?? form.errors.end_time
                            }
                        />
                    </SettingRow>
                    <SettingRow label="Venue" htmlFor="location_name">
                        <Input
                            {...field('location_name')}
                            placeholder="e.g. Cinnamon Grand, Colombo"
                        />
                        <FieldError message={form.errors.location_name} />
                    </SettingRow>
                    <SettingRow label="Address" htmlFor="location_address">
                        <Textarea {...field('location_address')} rows={2} />
                        <FieldError message={form.errors.location_address} />
                    </SettingRow>
                    <SettingRow
                        label="Map"
                        hint="Paste a Google Maps link to show the map on the invitation."
                        htmlFor="map_url"
                    >
                        <Input
                            type="url"
                            {...field('map_url')}
                            placeholder="https://maps.app.goo.gl/…"
                        />
                        <FieldError message={form.errors.map_url} />
                        <div className="mt-3 overflow-hidden rounded-xl empty:hidden">
                            <MapPreview
                                mapUrl={form.data.map_url}
                                previewUrl={mapPreviewUrl}
                            />
                        </div>
                    </SettingRow>
                </>
            ),
        },
        {
            id: 'access',
            title: 'Who can join',
            description:
                'Only your guest list, or anyone with the public link.',
            icon: DoorOpen,
            placement: 'start',
            hasError: !!form.errors.registration_type,
            content: (
                <SettingRow stacked>
                    <RegistrationTypePicker
                        options={registrationTypes}
                        value={form.data.registration_type}
                        onChange={(type) =>
                            form.setData('registration_type', type)
                        }
                        error={form.errors.registration_type}
                    />
                    {event &&
                        event.registration_type !== 'guest_list_only' &&
                        form.data.registration_type === 'guest_list_only' && (
                            <p className="mt-3 rounded-xl bg-warning-muted px-3 py-2 text-xs">
                                The public link stops accepting registrations
                                and shows an "invited guests only" page. Guests
                                already on the list stay.
                            </p>
                        )}
                </SettingRow>
            ),
        },
        ...extras('access'),
        {
            id: 'messages',
            title: 'Messages',
            description:
                'What guests get with their RSVP link, and in reminders. The subject is for email only.',
            icon: MessageCircle,
            placement: 'start',
            hasError: !!(
                form.errors.invitation_message ||
                form.errors.reminder_message ||
                form.errors.invitation_subject ||
                form.errors.reminder_subject
            ),
            content: (
                <>
                    {event?.message_limits && (
                        <SettingRow stacked>
                            <p className="rounded-xl bg-raised px-3 py-2 text-xs text-muted-foreground">
                                Each guest can get up to{' '}
                                {event.message_limits.invitations}{' '}
                                {event.message_limits.invitations === 1
                                    ? 'invitation'
                                    : 'invitations'}{' '}
                                and {event.message_limits.reminders}{' '}
                                {event.message_limits.reminders === 1
                                    ? 'reminder'
                                    : 'reminders'}{' '}
                                (automatic reminders included). Contact EMP to
                                change this.
                            </p>
                        </SettingRow>
                    )}
                    <SettingRow stacked>
                        <MessageField
                            id="invitation_message"
                            label="Invitation"
                            hint="Sent with each guest's RSVP link, unless the guest has a custom message. Leave empty to use the text shown."
                            value={form.data.invitation_message}
                            onChange={(value) =>
                                form.setData('invitation_message', value)
                            }
                            error={form.errors.invitation_message}
                            defaultMessage={defaultInvitationMessage}
                            sample={messageSample}
                            subject={{
                                value: form.data.invitation_subject,
                                onChange: (value) =>
                                    form.setData('invitation_subject', value),
                                defaultSubject: defaultInvitationSubject,
                                error: form.errors.invitation_subject,
                            }}
                        />
                    </SettingRow>
                    <SettingRow stacked>
                        <MessageField
                            id="reminder_message"
                            label="Reminder"
                            hint="Sent when you remind a guest who hasn't replied, or by automatic reminders. Leave empty to use the text shown."
                            value={form.data.reminder_message}
                            onChange={(value) =>
                                form.setData('reminder_message', value)
                            }
                            error={form.errors.reminder_message}
                            defaultMessage={defaultReminderMessage}
                            sample={messageSample}
                            subject={{
                                value: form.data.reminder_subject,
                                onChange: (value) =>
                                    form.setData('reminder_subject', value),
                                defaultSubject: defaultReminderSubject,
                                error: form.errors.reminder_subject,
                            }}
                        />
                    </SettingRow>
                </>
            ),
        },
        {
            id: 'capacity',
            title: 'Capacity',
            description: 'How many guests can come.',
            icon: Users,
            placement: 'start',
            hasError: !!form.errors.max_capacity,
            content: (
                <SettingRow
                    label="Limit the guest count"
                    hint={
                        Number(form.data.max_capacity) > 0
                            ? 'Registrations stop once it’s full.'
                            : 'Off: no limit besides your plan’s.'
                    }
                    control={
                        <Switch
                            checked={Number(form.data.max_capacity) > 0}
                            onChange={(on) =>
                                form.setData('max_capacity', on ? 100 : 0)
                            }
                        />
                    }
                >
                    {Number(form.data.max_capacity) > 0 && (
                        <div className="flex items-center gap-2">
                            <Input
                                type="number"
                                min={1}
                                aria-label="Capacity"
                                className="w-28"
                                {...field('max_capacity')}
                            />
                            <span className="text-sm text-muted-foreground">
                                guests at most
                            </span>
                        </div>
                    )}
                    <FieldError message={form.errors.max_capacity} />
                </SettingRow>
            ),
        },
        {
            id: 'reminders',
            title: 'Reminders',
            description: 'Nudge guests who haven’t replied.',
            icon: BellRing,
            placement: 'start',
            hasError: !!(
                form.errors.remind_after_days || form.errors.remind_before_days
            ),
            content: (
                <>
                    <SettingRow
                        label="Automatic reminders"
                        hint="On WhatsApp, never twice within a day."
                        control={
                            <Switch
                                checked={form.data.auto_reminders}
                                onChange={(on) =>
                                    form.setData('auto_reminders', on)
                                }
                            />
                        }
                    />
                    {form.data.auto_reminders && (
                        <>
                            <SettingRow
                                label="After the invitation"
                                hint="If they still haven’t replied."
                                control={
                                    <DaysStepper
                                        value={Number(
                                            form.data.remind_after_days || 1,
                                        )}
                                        min={1}
                                        onChange={(value) =>
                                            form.setData(
                                                'remind_after_days',
                                                value,
                                            )
                                        }
                                    />
                                }
                            >
                                <FieldError
                                    message={form.errors.remind_after_days}
                                />
                            </SettingRow>
                            <SettingRow
                                label="Before the event"
                                hint={
                                    eventDate
                                        ? 'A last nudge before the day.'
                                        : 'Needs an event date.'
                                }
                                control={
                                    <DaysStepper
                                        value={Number(
                                            form.data.remind_before_days || 0,
                                        )}
                                        min={0}
                                        onChange={(value) =>
                                            form.setData(
                                                'remind_before_days',
                                                value,
                                            )
                                        }
                                    />
                                }
                            >
                                <FieldError
                                    message={form.errors.remind_before_days}
                                />
                            </SettingRow>
                        </>
                    )}
                </>
            ),
        },
        ...extras('end'),
    ];

    const byId = (ids: string[]) =>
        ids
            .map((id) => sections.find((section) => section.id === id))
            .filter((section) => section !== undefined);
    const steps: Step[] = [
        {
            id: 'basics',
            label: 'Basics',
            icon: Sparkles,
            sections: byId(['design', 'details']),
        },
        {
            id: 'when',
            label: 'When & where',
            icon: CalendarDays,
            sections: byId(['when']),
        },
        {
            id: 'access',
            label: 'Access',
            icon: DoorOpen,
            sections: byId([
                'access',
                ...extras('access').map((section) => section.id),
            ]),
        },
        {
            id: 'messages',
            label: 'Messages',
            icon: MessageCircle,
            sections: byId(['messages']),
        },
        {
            id: 'extras',
            label: 'Extras',
            icon: Users,
            sections: byId(['capacity', 'reminders']),
        },
        ...(extras('start').length + extras('end').length > 0
            ? [
                  {
                      id: 'publish',
                      label: 'Publish',
                      icon: Rocket,
                      sections: [...extras('start'), ...extras('end')],
                  },
              ]
            : []),
    ];
    const [stepIndex, setStepIndex] = useState(0);
    const step = steps[Math.min(stepIndex, steps.length - 1)];
    const goTo = (index: number) => {
        setStepIndex(index);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    const firstStepWithError = steps.findIndex((item) =>
        item.sections.some((section) => section.hasError),
    );

    // After a failed save, show the first step with a problem.
    useEffect(() => {
        if (firstStepWithError >= 0) {
            setStepIndex(firstStepWithError);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.errors]);

    return (
        <form
            className="mx-auto grid w-full max-w-3xl gap-6 pb-28"
            onSubmit={(e) => {
                e.preventDefault();
                // Without the design picker the design is not part of this form.
                form.transform((data) =>
                    choosesTemplate
                        ? data
                        : Object.fromEntries(
                              Object.entries(data).filter(
                                  ([key]) => key !== 'template_id',
                              ),
                          ),
                );
                onSubmit(form);
            }}
        >
            <StepRail steps={steps} current={stepIndex} onGo={goTo} />

            <div
                key={step.id}
                className="grid min-w-0 gap-8 motion-safe:animate-fade-in"
            >
                {step.sections.map((section) => (
                    <section key={section.id} className="grid gap-3">
                        <div className="flex items-start gap-3 px-1">
                            <section.icon
                                className={cn(
                                    'mt-0.5 size-5 shrink-0',
                                    section.danger
                                        ? 'text-destructive'
                                        : 'text-muted-foreground',
                                )}
                            />
                            <div>
                                <h2 className="text-base font-bold">
                                    {section.title}
                                </h2>
                                {section.description && (
                                    <p className="text-sm text-muted-foreground">
                                        {section.description}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div
                            className={cn(
                                'relative divide-y divide-border rounded-2xl bg-card shadow-card',
                                section.danger && 'ring-1 ring-destructive/30',
                            )}
                        >
                            {section.content}
                        </div>
                    </section>
                ))}
            </div>

            <div className="flex items-center justify-between gap-3">
                <button
                    type="button"
                    onClick={() => goTo(stepIndex - 1)}
                    disabled={stepIndex === 0}
                    className="inline-flex h-10 items-center gap-1.5 rounded-full px-4 text-sm font-semibold text-muted-foreground hover:bg-raised hover:text-foreground disabled:invisible"
                >
                    <ArrowLeft className="size-4" /> Back
                </button>
                <span className="text-xs text-subtle">
                    Step {stepIndex + 1} of {steps.length}
                </span>
                {stepIndex < steps.length - 1 ? (
                    <button
                        type="button"
                        onClick={() => goTo(stepIndex + 1)}
                        className="inline-flex h-10 items-center gap-1.5 rounded-full bg-strong px-5 text-sm font-bold text-strong-foreground"
                    >
                        {steps[stepIndex + 1].label}
                        <ArrowRight className="size-4" />
                    </button>
                ) : (
                    <span className="w-24" />
                )}
            </div>

            {/* Editing: shown only while there's something to save. Creating: always (it holds the create button). */}
            <div
                className={cn(
                    'fixed inset-x-4 bottom-24 z-30 mx-auto flex max-w-md items-center gap-3 rounded-full bg-hero py-2 pr-2 pl-5 text-hero-foreground shadow-2xl ring-1 ring-hero-edge transition-all duration-300 md:bottom-6',
                    !event || form.isDirty || form.processing
                        ? 'translate-y-0 opacity-100'
                        : 'pointer-events-none translate-y-4 opacity-0',
                )}
                aria-hidden={!!event && !form.isDirty && !form.processing}
            >
                <span className="min-w-0 flex-1 truncate text-sm font-semibold">
                    {form.processing
                        ? 'Saving…'
                        : !event
                          ? stepIndex < steps.length - 1
                              ? 'Fill in what you know; the rest can wait'
                              : 'Ready when you are'
                          : 'You have unsaved changes'}
                </span>
                {event && form.isDirty && !form.processing && (
                    <button
                        type="button"
                        onClick={() => form.reset()}
                        className="h-9 shrink-0 rounded-full px-3 text-sm font-semibold opacity-80 hover:opacity-100"
                    >
                        Discard
                    </button>
                )}
                <button
                    type="submit"
                    disabled={form.processing || (!!event && !form.isDirty)}
                    className="h-9 shrink-0 rounded-full bg-hero-accent px-5 text-sm font-bold text-hero disabled:opacity-50"
                >
                    {submitLabel}
                </button>
            </div>
        </form>
    );
}

type Step = {
    id: string;
    label: string;
    icon: LucideIcon;
    sections: (ExtraSection & { hasError?: boolean })[];
};

/**
 * The steps as a progress rail: numbered dots joined by a line, done ones
 * checked, the current one filled; any step can be opened. Scrolls sideways
 * on narrow screens (labels show from `sm` up, and for the current step).
 */
function StepRail({
    steps,
    current,
    onGo,
}: {
    steps: Step[];
    current: number;
    onGo: (index: number) => void;
}) {
    return (
        <nav
            aria-label="Steps"
            className="rounded-2xl bg-card p-3 shadow-card sm:p-4"
        >
            <ol className="flex items-start">
                {steps.map((item, index) => {
                    const done = index < current;
                    const active = index === current;
                    const hasError = item.sections.some(
                        (section) => section.hasError,
                    );

                    return (
                        <li
                            key={item.id}
                            className="relative flex min-w-0 flex-1 flex-col items-center"
                        >
                            {index > 0 && (
                                <span
                                    aria-hidden
                                    className={cn(
                                        'absolute top-4 right-1/2 h-0.5 w-full -translate-y-1/2',
                                        index <= current
                                            ? 'bg-primary'
                                            : 'bg-foreground/10',
                                    )}
                                />
                            )}
                            <button
                                type="button"
                                onClick={() => onGo(index)}
                                aria-current={active ? 'step' : undefined}
                                className="group relative flex min-w-0 flex-col items-center gap-1.5 px-1"
                            >
                                <span
                                    className={cn(
                                        'flex size-8 items-center justify-center rounded-full text-xs font-bold ring-4 ring-card transition-colors',
                                        hasError
                                            ? 'bg-destructive text-white'
                                            : active
                                              ? 'bg-primary text-primary-foreground'
                                              : done
                                                ? 'bg-primary/20 text-primary'
                                                : 'bg-raised text-muted-foreground group-hover:bg-foreground/10',
                                    )}
                                >
                                    {hasError ? (
                                        '!'
                                    ) : done ? (
                                        <Check className="size-4" />
                                    ) : (
                                        <item.icon className="size-4" />
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'max-w-full truncate text-[11px] font-semibold',
                                        active
                                            ? 'text-foreground'
                                            : 'hidden text-muted-foreground sm:block',
                                    )}
                                >
                                    {item.label}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}

/**
 * One setting: label and hint on the left, the control on the right (from
 * `sm` up; stacked on phones). `control` sits at the row's end (a switch);
 * `stacked` gives the content the full width.
 */
export function SettingRow({
    label,
    hint,
    htmlFor,
    control,
    stacked = false,
    children,
}: {
    label?: string;
    hint?: string;
    htmlFor?: string;
    control?: ReactNode;
    stacked?: boolean;
    children?: ReactNode;
}) {
    if (stacked) {
        return <div className="min-w-0 p-4 sm:p-5">{children}</div>;
    }

    return (
        <div className="grid min-w-0 gap-3 p-4 sm:grid-cols-[minmax(0,13rem)_minmax(0,1fr)] sm:gap-6 sm:p-5">
            <div className="flex min-w-0 items-start justify-between gap-3 sm:block">
                <div className="min-w-0">
                    {label &&
                        (htmlFor ? (
                            <label
                                htmlFor={htmlFor}
                                className="text-sm font-semibold"
                            >
                                {label}
                            </label>
                        ) : (
                            <span className="text-sm font-semibold">
                                {label}
                            </span>
                        ))}
                    {hint && (
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {hint}
                        </p>
                    )}
                </div>
                {control && <div className="shrink-0 sm:hidden">{control}</div>}
            </div>
            <div
                className={cn(
                    'min-w-0',
                    control &&
                        'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between',
                    control && !children && 'hidden sm:flex',
                )}
            >
                {children && <div className="min-w-0 flex-1">{children}</div>}
                {control && (
                    <div className="hidden shrink-0 sm:ml-auto sm:block">
                        {control}
                    </div>
                )}
            </div>
        </div>
    );
}

function DaysStepper({
    value,
    min,
    onChange,
}: {
    value: number;
    min: number;
    onChange: (value: number) => void;
}) {
    return (
        <span className="flex items-center gap-2">
            <Stepper
                label="Days"
                value={value}
                min={min}
                max={60}
                onChange={onChange}
            />
            <span className="text-xs text-muted-foreground">
                {value === 1 ? 'day' : 'days'}
            </span>
        </span>
    );
}

function FieldError({ message }: { message?: string }) {
    return message ? (
        <p className="mt-1 text-xs text-destructive">{message}</p>
    ) : null;
}
