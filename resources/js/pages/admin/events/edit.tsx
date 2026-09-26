import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Trash2 } from 'lucide-react';

import { EventForm } from '@/components/events/event-form';
import { MessageLimitsCard } from '@/components/events/message-limits-card';
import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { useStaffCan } from '@/lib/permissions';
import { mapPreview } from '@/routes/admin';
import { show } from '@/routes/admin/clients';
import { preview } from '@/routes/admin/clients/templates';
import { destroy, state, update } from '@/routes/admin/clients/events';
import { close, open } from '@/routes/admin/clients/events/registration';
import type {
    Client,
    Event,
    EventState,
    Option,
    RegistrationTypeOption,
    Resource,
    Template,
} from '@/types';

const transitionLabels: Record<EventState, string> = {
    published: 'Publish',
    draft: 'Unpublish',
    cancelled: 'Cancel event',
};

export default function AdminEditEvent({
    client: { data: client },
    event: { data: event },
    templates,
    eventTypes,
    registrationTypes,
    defaultInvitationMessage,
    defaultReminderMessage,
}: {
    client: Resource<Client>;
    event: Resource<Event>;
    templates: { data: Template[] };
    eventTypes: Option[];
    registrationTypes: RegistrationTypeOption[];
    defaultInvitationMessage: string;
    defaultReminderMessage: string;
}) {
    const can = useStaffCan();
    const route = { client: client.id, event: event.id };

    const transition = (next: EventState) => {
        if (
            next === 'cancelled' &&
            !confirm('Cancel this event? This cannot be undone.')
        ) {
            return;
        }

        router.patch(
            state.url(route),
            { state: next },
            { preserveScroll: true },
        );
    };

    const toggleRegistration = () =>
        router.post(
            (event.registration_open ? close : open).url(route),
            {},
            { preserveScroll: true },
        );

    const remove = () => {
        if (
            confirm(
                `Delete "${event.title}" and all its guests? This cannot be undone.`,
            )
        ) {
            router.delete(destroy.url(route));
        }
    };

    return (
        <AdminLayout>
            <Head title={`Edit ${event.title}`} />
            <PageHeader
                title={event.title}
                description={
                    <span className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={event.state} />
                        <span>for {client.name}</span>
                        {event.public_url && (
                            <a
                                href={event.public_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 hover:text-foreground"
                            >
                                {event.public_url}{' '}
                                <ExternalLink className="h-3 w-3" />
                            </a>
                        )}
                    </span>
                }
                actions={
                    <>
                        <Link
                            href={show.url(client)}
                            className={buttonVariants({ variant: 'ghost' })}
                        >
                            <ArrowLeft /> Client
                        </Link>
                        {event.allowed_transitions.map((next) => (
                            <Button
                                key={next}
                                variant={
                                    next === 'cancelled'
                                        ? 'destructive'
                                        : next === 'published'
                                          ? 'default'
                                          : 'outline'
                                }
                                onClick={() => transition(next)}
                            >
                                {transitionLabels[next]}
                            </Button>
                        ))}
                        {can('events.delete') && (
                            <Button
                                variant="ghost"
                                onClick={remove}
                                aria-label="Delete event"
                            >
                                <Trash2 />
                            </Button>
                        )}
                    </>
                }
            />

            <Card className="mb-6">
                <CardContent className="flex flex-wrap items-center justify-between gap-3 text-sm">
                    {event.registration_type === 'guest_list_only' ? (
                        <p className="text-muted-foreground">
                            <span className="font-medium text-foreground">
                                Guest list only
                            </span>
                            : no public registration. Only guests the client
                            adds can RSVP, through their personal link.
                        </p>
                    ) : (
                        <>
                            <p className="text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    {event.registration_type_label}
                                </span>
                                {' · '}Public registration is{' '}
                                <span className="font-medium text-foreground">
                                    {event.registration_open
                                        ? 'open'
                                        : 'closed'}
                                </span>
                                {event.registration_open &&
                                    event.state !== 'published' &&
                                    ' (guests can register once the event is published)'}
                                .
                            </p>
                            <Button
                                variant={
                                    event.registration_open
                                        ? 'outline'
                                        : 'default'
                                }
                                disabled={
                                    !event.registration_open &&
                                    event.state === 'cancelled'
                                }
                                onClick={toggleRegistration}
                            >
                                {event.registration_open
                                    ? 'Close registration'
                                    : 'Open registration'}
                            </Button>
                        </>
                    )}
                </CardContent>
            </Card>

            {can('events.update') && (
                <MessageLimitsCard event={event} clientId={client.id} />
            )}

            {event.state === 'cancelled' ? (
                <p className="text-sm text-muted-foreground">
                    Cancelled events can no longer be edited.
                </p>
            ) : (
                <EventForm
                    event={event}
                    templates={templates.data}
                    eventTypes={eventTypes}
                    registrationTypes={registrationTypes}
                    defaultInvitationMessage={defaultInvitationMessage}
                    defaultReminderMessage={defaultReminderMessage}
                    mapPreviewUrl={(url) => mapPreview.url({ query: { url } })}
                    templatePreviewUrl={(template) =>
                        preview.url({
                            client: client.id,
                            template: template.id,
                        })
                    }
                    submitLabel="Save changes"
                    onSubmit={(form) =>
                        form.patch(update.url(route), { preserveScroll: true })
                    }
                />
            )}
        </AdminLayout>
    );
}
