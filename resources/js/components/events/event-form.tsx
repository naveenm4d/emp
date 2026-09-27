import { useForm } from '@inertiajs/react';

import { MapPreview } from '@/components/events/map-preview';
import { MessageField } from '@/components/events/message-field';
import { RegistrationTypePicker } from '@/components/events/registration-type-picker';
import { FormField } from '@/components/shared/form-field';
import { TemplatePicker } from '@/components/templates/template-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { todayInAppTimeZone } from '@/lib/format';
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
    /** Endpoint that turns a map link into an embeddable map (client or admin area). */
    mapPreviewUrl: (mapUrl: string) => string;
    /** JSON preview endpoint for a template (client or admin area). */
    templatePreviewUrl?: (template: Template) => string;
    /** Preselected design when creating (e.g. chosen on the Templates page). */
    defaultTemplateId?: string | null;
    submitLabel: string;
    onSubmit: (form: ReturnType<typeof useForm<EventFormData>>) => void;
    /** Extra cards shown above the registration settings (right column). */
    aside?: React.ReactNode;
};

/** Create / edit form for an event. The parent decides which route to hit. */
export function EventForm({
    event,
    templates,
    eventTypes,
    registrationTypes,
    defaultInvitationMessage,
    defaultReminderMessage,
    mapPreviewUrl,
    templatePreviewUrl,
    defaultTemplateId,
    submitLabel,
    onSubmit,
    aside,
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

    return (
        <form
            className="grid gap-6 lg:grid-cols-3"
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
            <div className="space-y-6 lg:col-span-2">
                {choosesTemplate && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Invitation template</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Guests see your invitation in this design. You
                                fill in its photos, videos and music on the
                                Design tab.
                            </p>
                            {event?.template &&
                                form.data.template_id !== event.template.id && (
                                    <p className="text-xs text-warning">
                                        Switching templates keeps your uploads,
                                        but media the new template has no place
                                        for is not shown.
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
                            {form.errors.template_id && (
                                <p className="text-xs text-destructive">
                                    {form.errors.template_id}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <FormField
                            label="Title"
                            htmlFor="title"
                            error={form.errors.title}
                        >
                            <Input {...field('title')} autoFocus={!event} />
                        </FormField>
                        <FormField
                            label="URL slug"
                            htmlFor="slug"
                            error={form.errors.slug}
                            hint={
                                event
                                    ? `Shown in your links: ${window.location.host}/${form.data.slug || '…'}/… Links you already shared keep working if you change it.`
                                    : `Shown in your links: ${window.location.host}/${form.data.slug || 'your-slug'}/… Leave empty to generate one from the title. It doesn't need to be unique.`
                            }
                        >
                            <Input
                                {...field('slug')}
                                placeholder="john-and-amy"
                            />
                        </FormField>
                        <FormField
                            label="Description"
                            htmlFor="description"
                            error={form.errors.description}
                        >
                            <Textarea {...field('description')} rows={5} />
                        </FormField>
                        <FormField
                            label="Event type"
                            htmlFor="event_type"
                            error={form.errors.event_type}
                        >
                            <Select
                                id="event_type"
                                value={form.data.event_type}
                                aria-invalid={!!form.errors.event_type}
                                onChange={(e) =>
                                    form.setData('event_type', e.target.value)
                                }
                            >
                                <option value="">Select a type</option>
                                {eventTypes.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </Select>
                        </FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>When &amp; where</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Date"
                            htmlFor="event_date"
                            error={form.errors.event_date}
                        >
                            <Input
                                type="date"
                                min={event ? undefined : todayInAppTimeZone()}
                                {...field('event_date')}
                            />
                        </FormField>
                        <FormField
                            label="Starts"
                            htmlFor="start_time"
                            error={form.errors.start_time}
                        >
                            <Input type="time" {...field('start_time')} />
                        </FormField>
                        <FormField
                            label="Ends"
                            htmlFor="end_time"
                            error={form.errors.end_time}
                        >
                            <Input
                                type="time"
                                min={form.data.start_time || undefined}
                                {...field('end_time')}
                            />
                        </FormField>
                        <FormField
                            label="Venue"
                            htmlFor="location_name"
                            error={form.errors.location_name}
                            className="sm:col-span-3"
                        >
                            <Input {...field('location_name')} />
                        </FormField>
                        <FormField
                            label="Address"
                            htmlFor="location_address"
                            error={form.errors.location_address}
                            className="sm:col-span-3"
                        >
                            <Textarea {...field('location_address')} rows={2} />
                        </FormField>
                        <FormField
                            label="Map link"
                            htmlFor="map_url"
                            error={form.errors.map_url}
                            className="sm:col-span-3"
                        >
                            <Input
                                type="url"
                                {...field('map_url')}
                                placeholder="https://maps.app.goo.gl/…"
                            />
                        </FormField>
                        <div className="sm:col-span-3">
                            <MapPreview
                                mapUrl={form.data.map_url}
                                previewUrl={mapPreviewUrl}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Who can join</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
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
                            form.data.registration_type ===
                                'guest_list_only' && (
                                <p className="text-xs text-warning">
                                    The public link stops accepting
                                    registrations and shows an "invited guests
                                    only" page. Guests already on the list stay.
                                </p>
                            )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Guest messages</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {event?.message_limits && (
                            <p className="rounded-lg bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
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
                        )}
                        <MessageField
                            id="invitation_message"
                            label="Invitation (WhatsApp)"
                            hint="Sent with each guest's RSVP link, unless the guest has a custom message. Leave empty to use the text shown."
                            value={form.data.invitation_message}
                            onChange={(value) =>
                                form.setData('invitation_message', value)
                            }
                            error={form.errors.invitation_message}
                            defaultMessage={defaultInvitationMessage}
                            sample={messageSample}
                        />
                        <MessageField
                            id="reminder_message"
                            label="Reminder (WhatsApp)"
                            hint="Sent when you remind a guest who hasn't replied, or by automatic reminders. Leave empty to use the text shown."
                            value={form.data.reminder_message}
                            onChange={(value) =>
                                form.setData('reminder_message', value)
                            }
                            error={form.errors.reminder_message}
                            defaultMessage={defaultReminderMessage}
                            sample={messageSample}
                        />
                    </CardContent>
                </Card>
            </div>

            <div className="space-y-6">
                {aside}
                <Card>
                    <CardHeader>
                        <CardTitle>Capacity</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <FormField
                            label="Capacity"
                            htmlFor="max_capacity"
                            error={form.errors.max_capacity}
                            hint="0 means unlimited."
                        >
                            <Input
                                type="number"
                                min={0}
                                {...field('max_capacity')}
                            />
                        </FormField>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Reminders</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="mt-0.5"
                                checked={form.data.auto_reminders}
                                onChange={(e) =>
                                    form.setData(
                                        'auto_reminders',
                                        e.target.checked,
                                    )
                                }
                            />
                            <span>
                                Send automatic reminders
                                <span className="block text-xs text-muted-foreground">
                                    Guests who haven't replied get your reminder
                                    message on WhatsApp. Each reminder goes out
                                    once, never twice within a day.
                                </span>
                            </span>
                        </label>
                        {form.data.auto_reminders && (
                            <div className="grid gap-4">
                                <FormField
                                    label="Days after sending the invitation"
                                    htmlFor="remind_after_days"
                                    error={form.errors.remind_after_days}
                                    hint="If the guest still hasn't replied."
                                >
                                    <Input
                                        type="number"
                                        min={1}
                                        max={60}
                                        {...field('remind_after_days')}
                                    />
                                </FormField>
                                <FormField
                                    label="Days before the event"
                                    htmlFor="remind_before_days"
                                    error={form.errors.remind_before_days}
                                    hint={
                                        form.data.event_date
                                            ? 'A last nudge to reply before the day.'
                                            : 'Needs an event date.'
                                    }
                                >
                                    <Input
                                        type="number"
                                        min={0}
                                        max={60}
                                        {...field('remind_before_days')}
                                    />
                                </FormField>
                            </div>
                        )}
                    </CardContent>
                </Card>
                <Button
                    type="submit"
                    size="lg"
                    className="w-full"
                    disabled={form.processing}
                >
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
