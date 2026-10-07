import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { EventForm } from '@/components/events/event-form';
import { PageHeader } from '@/components/shared/page-header';
import { buttonVariants } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin-layout';
import { mapPreview } from '@/routes/admin';
import { show } from '@/routes/admin/clients';
import { preview } from '@/routes/admin/clients/templates';
import { store } from '@/routes/admin/clients/events';
import type {
    Client,
    Option,
    RegistrationTypeOption,
    Resource,
    Template,
} from '@/types';

export default function AdminCreateEvent({
    client: { data: client },
    templates,
    eventTypes,
    registrationTypes,
    defaultInvitationMessage,
    defaultReminderMessage,
    defaultInvitationSubject,
    defaultReminderSubject,
}: {
    client: Resource<Client>;
    templates: { data: Template[] };
    eventTypes: Option[];
    registrationTypes: RegistrationTypeOption[];
    defaultInvitationMessage: string;
    defaultReminderMessage: string;
    defaultInvitationSubject: string;
    defaultReminderSubject: string;
}) {
    return (
        <AdminLayout>
            <Head title={`New event for ${client.name}`} />
            <PageHeader
                title="New event"
                description={`For ${client.name} (${client.email}). Events start as drafts.`}
                actions={
                    <Link
                        href={show.url(client)}
                        className={buttonVariants({ variant: 'outline' })}
                    >
                        <ArrowLeft /> Back to client
                    </Link>
                }
            />
            <EventForm
                templates={templates.data}
                eventTypes={eventTypes}
                registrationTypes={registrationTypes}
                defaultInvitationMessage={defaultInvitationMessage}
                defaultReminderMessage={defaultReminderMessage}
                defaultInvitationSubject={defaultInvitationSubject}
                defaultReminderSubject={defaultReminderSubject}
                mapPreviewUrl={(url) => mapPreview.url({ query: { url } })}
                templatePreviewUrl={(template) =>
                    preview.url({ client: client.id, template: template.id })
                }
                submitLabel="Create event"
                onSubmit={(form) => form.post(store.url(client))}
            />
        </AdminLayout>
    );
}
