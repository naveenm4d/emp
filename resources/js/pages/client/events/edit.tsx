import { Head } from '@inertiajs/react';

import { EventForm } from '@/components/events/event-form';
import { PageHeader } from '@/components/shared/page-header';
import ClientLayout from '@/layouts/client-layout';
import { update } from '@/routes/client/events';
import type { Event, Resource, Template } from '@/types';

export default function EditEvent({
    event,
    templates,
}: {
    event: Resource<Event>;
    templates: { data: Template[] };
}) {
    return (
        <ClientLayout>
            <Head title={`Edit ${event.data.title}`} />
            <PageHeader title="Edit event" description={event.data.title} />
            <EventForm
                event={event.data}
                templates={templates.data}
                submitLabel="Save changes"
                onSubmit={(form) => form.patch(update.url(event.data))}
            />
        </ClientLayout>
    );
}
