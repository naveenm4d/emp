import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    Clock,
    Copy,
    ExternalLink,
    MapPin,
    Trash2,
    Users,
} from 'lucide-react';

import { EventForm } from '@/components/events/event-form';
import { RegistrationTypeDetails } from '@/components/events/registration-type-picker';
import { EventTabs } from '@/components/events/event-tabs';
import { MetricCard } from '@/components/shared/metric-card';
import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ClientLayout from '@/layouts/client-layout';
import { formatDate } from '@/lib/format';
import { showsApproval } from '@/lib/guests';
import { cn } from '@/lib/utils';
import { mapPreview } from '@/routes/client';
import { destroy, state, update } from '@/routes/client/events';
import { close, open } from '@/routes/client/events/registration';
import type {
    Event,
    EventState,
    GuestSummary,
    Option,
    RegistrationTypeOption,
    Resource,
    RsvpSummary,
} from '@/types';

type Props = {
    event: Resource<Event>;
    guestSummary: GuestSummary;
    rsvpSummary: RsvpSummary;
    eventTypes: Option[];
    registrationTypes: RegistrationTypeOption[];
    defaultInvitationMessage: string;
    defaultReminderMessage: string;
};

const transitionLabels: Record<EventState, string> = {
    published: 'Publish',
    draft: 'Unpublish',
    cancelled: 'Cancel event',
};

export default function ShowEvent({
    event: { data: event },
    guestSummary,
    rsvpSummary,
    eventTypes,
    registrationTypes,
    defaultInvitationMessage,
    defaultReminderMessage,
}: Props) {
    const approval = showsApproval(event, guestSummary);

    const transition = (next: EventState) => {
        if (
            next === 'cancelled' &&
            !confirm('Cancel this event? This cannot be undone.')
        ) {
            return;
        }

        router.patch(
            state.url(event),
            { state: next },
            { preserveScroll: true },
        );
    };

    const remove = () => {
        if (
            confirm(
                `Delete "${event.title}" and all its guests? This cannot be undone.`,
            )
        ) {
            router.delete(destroy.url(event));
        }
    };

    return (
        <ClientLayout>
            <Head title={event.title} />
            <PageHeader
                title={event.title}
                description={
                    <span className="flex items-center gap-2">
                        <StatusBadge status={event.state} />
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
                        <Button
                            variant="ghost"
                            onClick={remove}
                            aria-label="Delete event"
                        >
                            <Trash2 />
                        </Button>
                    </>
                }
            />

            <EventTabs event={event} />

            <div
                className={cn(
                    'mb-6 grid gap-4 sm:grid-cols-2',
                    approval ? 'xl:grid-cols-4' : 'xl:grid-cols-3',
                )}
            >
                <MetricCard
                    title="Guests"
                    value={guestSummary.total}
                    icon={Users}
                    description={
                        event.max_capacity
                            ? `Capacity ${event.max_capacity}`
                            : 'Unlimited capacity'
                    }
                />
                {approval && (
                    <MetricCard
                        title="Pending approval"
                        value={guestSummary.pending}
                        description={`${guestSummary.waitlisted} waitlisted`}
                    />
                )}
                <MetricCard
                    title="RSVP links sent"
                    value={
                        rsvpSummary.sent +
                        rsvpSummary.accepted +
                        rsvpSummary.declined
                    }
                    description={`${rsvpSummary.pending} not sent yet`}
                />
                <MetricCard
                    title="Confirmed"
                    value={guestSummary.rsvp_confirmed}
                    description={`${guestSummary.rsvp_declined} declined`}
                />
            </div>

            {event.state === 'cancelled' ? (
                <div className="grid gap-6 lg:grid-cols-3">
                    <EventSummary event={event} />
                    <RegistrationCard
                        event={event}
                        registrationTypes={registrationTypes}
                    />
                </div>
            ) : (
                <EventForm
                    key={event.updated_at}
                    event={event}
                    eventTypes={eventTypes}
                    registrationTypes={registrationTypes}
                    defaultInvitationMessage={defaultInvitationMessage}
                    defaultReminderMessage={defaultReminderMessage}
                    mapPreviewUrl={(url) => mapPreview.url({ query: { url } })}
                    submitLabel="Save changes"
                    onSubmit={(form) =>
                        form.patch(update.url(event), { preserveScroll: true })
                    }
                    aside={
                        <RegistrationCard
                            event={event}
                            registrationTypes={registrationTypes}
                        />
                    }
                />
            )}
        </ClientLayout>
    );
}

/** Read-only details, for events that can no longer be edited. */
function EventSummary({ event }: { event: Event }) {
    return (
        <Card className="lg:col-span-2">
            <CardHeader>
                <CardTitle>About</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <p className="whitespace-pre-line text-muted-foreground">
                    {event.description || 'No description yet.'}
                </p>
                <dl className="grid gap-3 text-sm sm:grid-cols-3">
                    <Detail
                        icon={CalendarDays}
                        label="Date"
                        value={formatDate(event.event_date)}
                    />
                    <Detail
                        icon={Clock}
                        label="Time"
                        value={
                            event.start_time
                                ? `${event.start_time}${event.end_time ? ` – ${event.end_time}` : ''}`
                                : '—'
                        }
                    />
                    <Detail
                        icon={MapPin}
                        label="Venue"
                        value={event.location_name || '—'}
                    />
                </dl>
            </CardContent>
        </Card>
    );
}

/** The event's registration type (what it means) and, when it has public registration, the open/close switch. */
function RegistrationCard({
    event,
    registrationTypes,
}: {
    event: Event;
    registrationTypes: RegistrationTypeOption[];
}) {
    const option = registrationTypes.find(
        (type) => type.value === event.registration_type,
    );
    const hasPublicRegistration = event.registration_type !== 'guest_list_only';

    return (
        <Card>
            <CardHeader>
                <CardTitle>Registration</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
                <div>
                    <p className="font-medium">
                        {event.registration_type_label}
                    </p>
                    {option && (
                        <p className="text-xs text-muted-foreground">
                            {option.description}
                        </p>
                    )}
                </div>
                {option && <RegistrationTypeDetails option={option} />}
                {hasPublicRegistration && (
                    <>
                        {event.public_url && (
                            <div className="space-y-1 border-t border-border pt-3">
                                <p className="text-xs font-medium">
                                    Public link
                                </p>
                                <div className="flex items-center gap-1">
                                    <code className="min-w-0 flex-1 truncate rounded bg-muted px-2 py-1 text-xs">
                                        {event.public_url}
                                    </code>
                                    <Button
                                        type="button"
                                        size="icon-sm"
                                        variant="ghost"
                                        title="Copy public link"
                                        onClick={() =>
                                            navigator.clipboard.writeText(
                                                event.public_url ?? '',
                                            )
                                        }
                                    >
                                        <Copy />
                                    </Button>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Share it anywhere. Opened{' '}
                                    {event.public_link_open_count ?? 0} times.
                                </p>
                            </div>
                        )}
                        <p className="border-t border-border pt-3 text-muted-foreground">
                            {event.registration_open
                                ? event.state === 'published'
                                    ? 'Guests can register through the public event page.'
                                    : 'Registration is open, but guests can only register once the event is published.'
                                : 'Registration is paused: guests cannot register themselves right now.'}
                        </p>
                        {event.registration_open ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                onClick={() =>
                                    router.post(
                                        close.url(event),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Close registration
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                className="w-full"
                                disabled={event.state === 'cancelled'}
                                onClick={() =>
                                    router.post(
                                        open.url(event),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Open registration
                            </Button>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}

function Detail({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof CalendarDays;
    label: string;
    value: React.ReactNode;
}) {
    return (
        <div className="flex items-start gap-2">
            <Icon className="mt-0.5 h-4 w-4 text-muted-foreground" />
            <div>
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="font-medium">{value}</dd>
            </div>
        </div>
    );
}
