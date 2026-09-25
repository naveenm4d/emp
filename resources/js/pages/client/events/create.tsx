import { Head } from '@inertiajs/react';

import { EventForm } from '@/components/events/event-form';
import { PageHeader } from '@/components/shared/page-header';
import ClientLayout from '@/layouts/client-layout';
import { store } from '@/routes/client/events';
import type { Template } from '@/types';

export default function CreateEvent({
    templates,
}: {
    templates: { data: Template[] };
}) {
    return (
        <ClientLayout>
            <Head title="New event" />
            <PageHeader
                title="New event"
                description="Events start as drafts. Publish when you are ready to share."
            />
            <EventForm
                templates={templates.data}
                submitLabel="Create event"
                onSubmit={(form) => form.post(store.url())}
            />
        </ClientLayout>
    );
}
