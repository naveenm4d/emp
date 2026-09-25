import { useForm } from '@inertiajs/react';

import { FormField } from '@/components/shared/form-field';
import { TemplatePicker } from '@/components/templates/template-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { Event, Template } from '@/types';

export type EventFormData = {
    title: string;
    slug: string;
    template_id: string;
    description: string;
    max_capacity: number | '';
    require_approval: boolean;
    event_type: string;
    location_name: string;
    location_address: string;
    map_url: string;
    event_date: string;
    start_time: string;
    end_time: string;
};

type EventFormProps = {
    event?: Event;
    templates: Template[];
    submitLabel: string;
    onSubmit: (form: ReturnType<typeof useForm<EventFormData>>) => void;
};

/** Create / edit form for an event. The parent decides which route to hit. */
export function EventForm({
    event,
    templates,
    submitLabel,
    onSubmit,
}: EventFormProps) {
    const form = useForm<EventFormData>({
        title: event?.title ?? '',
        slug: event?.slug ?? '',
        template_id: event?.template?.id ?? '',
        description: event?.description ?? '',
        max_capacity: event?.max_capacity ?? 0,
        require_approval: event?.require_approval ?? false,
        event_type: event?.event_type ?? '',
        location_name: event?.location_name ?? '',
        location_address: event?.location_address ?? '',
        map_url: event?.map_url ?? '',
        event_date: event?.event_date ?? '',
        start_time: event?.start_time ?? '',
        end_time: event?.end_time ?? '',
    });

    const field = (
        key: Exclude<keyof EventFormData, 'require_approval' | 'template_id'>,
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
                onSubmit(form);
            }}
        >
            <div className="space-y-6 lg:col-span-2">
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
                                    ? 'Changing the slug breaks links you already shared.'
                                    : 'Leave empty to generate one from the title.'
                            }
                        >
                            <Input
                                {...field('slug')}
                                placeholder="summer-gala-2026"
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
                            hint="e.g. wedding, conference, birthday"
                        >
                            <Input {...field('event_type')} />
                        </FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Invitation template</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            Guests see your invitation in this design. You fill
                            in its photos, videos and music on the Design tab.
                        </p>
                        {event?.template &&
                            form.data.template_id !== event.template.id && (
                                <p className="text-xs text-amber-700 dark:text-amber-400">
                                    Switching templates keeps your uploads, but
                                    media the new template has no place for is
                                    not shown.
                                </p>
                            )}
                        <TemplatePicker
                            templates={templates}
                            value={form.data.template_id}
                            onChange={(id) => form.setData('template_id', id)}
                            invalid={!!form.errors.template_id}
                        />
                        {form.errors.template_id && (
                            <p className="text-xs text-destructive">
                                {form.errors.template_id}
                            </p>
                        )}
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
                            <Input type="date" {...field('event_date')} />
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
                            <Input type="time" {...field('end_time')} />
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
                                placeholder="https://maps.google.com/…"
                            />
                        </FormField>
                    </CardContent>
                </Card>
            </div>

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Registration</CardTitle>
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
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="mt-0.5"
                                checked={form.data.require_approval}
                                onChange={(e) =>
                                    form.setData(
                                        'require_approval',
                                        e.target.checked,
                                    )
                                }
                            />
                            <span>
                                Require approval
                                <span className="block text-xs text-muted-foreground">
                                    New guests start as pending until you
                                    approve them.
                                </span>
                            </span>
                        </label>
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
