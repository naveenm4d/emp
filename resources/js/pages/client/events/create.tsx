import { Head } from '@inertiajs/react';

import { EventForm } from '@/components/events/event-form';
import { PageHeader } from '@/components/shared/page-header';
import ClientLayout from '@/layouts/client-layout';
import { mapPreview } from '@/routes/client';
import { store } from '@/routes/client/events';
import { preview } from '@/routes/client/templates';
import type { Option, RegistrationTypeOption, Template } from '@/types';

export default function CreateEvent({
    templates,
    eventTypes,
    registrationTypes,
    defaultInvitationMessage,
    defaultReminderMessage,
    selectedTemplateId,
}: {
    templates: { data: Template[] };
    eventTypes: Option[];
    registrationTypes: RegistrationTypeOption[];
    defaultInvitationMessage: string;
    defaultReminderMessage: string;
    selectedTemplateId: string | null;
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
                eventTypes={eventTypes}
                registrationTypes={registrationTypes}
                defaultInvitationMessage={defaultInvitationMessage}
                defaultReminderMessage={defaultReminderMessage}
                mapPreviewUrl={(url) => mapPreview.url({ query: { url } })}
                templatePreviewUrl={(template) => preview.url(template)}
                defaultTemplateId={selectedTemplateId}
                submitLabel="Create event"
                onSubmit={(form) => form.post(store.url())}
            />
        </ClientLayout>
    );
}
