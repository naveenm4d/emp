import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    Clock,
    ExternalLink,
    MapPin,
    Pencil,
    Trash2,
    Users,
} from 'lucide-react';

import { EventTabs } from '@/components/events/event-tabs';
import { MetricCard } from '@/components/shared/metric-card';
import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ClientLayout from '@/layouts/client-layout';
import { formatDate } from '@/lib/format';
import { destroy, edit, state } from '@/routes/client/events';
import { close, open } from '@/routes/client/events/registration';
import type {
    Event,
    EventState,
    GuestSummary,
    InvitationSummary,
    Resource,
} from '@/types';

type Props = {
    event: Resource<Event>;
    guestSummary: GuestSummary;
    invitationSummary: InvitationSummary;
};

const transitionLabels: Record<EventState, string> = {
    published: 'Publish',
    draft: 'Unpublish',
    cancelled: 'Cancel event',
};

export default function ShowEvent({
    event: { data: event },
    guestSummary,
    invitationSummary,
}: Props) {
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
                        <a
                            href={event.public_url}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 hover:text-foreground"
                        >
                            {event.public_url}{' '}
                            <ExternalLink className="h-3 w-3" />
                        </a>
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
                        {event.state !== 'cancelled' && (
                            <Link
                                href={edit.url(event)}
                                className={buttonVariants({
                                    variant: 'outline',
                                })}
                            >
                                <Pencil /> Edit
                            </Link>
                        )}
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

            <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                <MetricCard
                    title="Pending approval"
                    value={guestSummary.pending}
                    description={`${guestSummary.waitlisted} waitlisted`}
                />
                <MetricCard
                    title="Invitations sent"
                    value={
                        invitationSummary.sent +
                        invitationSummary.accepted +
                        invitationSummary.declined
                    }
                    description={`${invitationSummary.pending} not sent yet`}
                />
                <MetricCard
                    title="Confirmed"
                    value={guestSummary.rsvp_confirmed}
                    description={`${guestSummary.rsvp_declined} declined`}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
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
                                value={
                                    event.map_url ? (
                                        <a
                                            href={event.map_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-primary hover:underline"
                                        >
                                            {event.location_name || 'Map'}
                                        </a>
                                    ) : (
                                        event.location_name || '—'
                                    )
                                }
                            />
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Public registration</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <p className="text-muted-foreground">
                            {event.registration_open
                                ? event.state === 'published'
                                    ? 'Guests can register through the public event page.'
                                    : 'Registration is open, but guests can only register once the event is published.'
                                : 'Guests cannot register themselves right now.'}
                        </p>
                        {event.registration_open ? (
                            <Button
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
                        {event.require_approval && (
                            <p className="text-xs text-muted-foreground">
                                New registrations need your approval.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </ClientLayout>
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
