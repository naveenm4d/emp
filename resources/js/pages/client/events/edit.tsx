import { Head, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    Ban,
    CalendarDays,
    CircleCheck,
    CircleDashed,
    Copy,
    EyeOff,
    Globe,
    Radio,
    Rocket,
    ShieldAlert,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

import { EventForm, SettingRow } from '@/components/events/event-form';
import type { ExtraSection } from '@/components/events/event-form';
import { RegistrationTypeDetails } from '@/components/events/registration-type-picker';
import { ConfirmBar, useConfirm } from '@/components/shared/confirm-bar';
import { Switch } from '@/components/shared/form-section';
import { PageHeader } from '@/components/shared/page-header';
import EventLayout from '@/layouts/event-layout';
import { formatDate, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { mapPreview } from '@/routes/client';
import { destroy, state, update } from '@/routes/client/events';
import { close, open } from '@/routes/client/events/registration';
import type {
    Event,
    EventState,
    Option,
    RegistrationTypeOption,
    Resource,
} from '@/types';

type Props = {
    event: Resource<Event>;
    eventTypes: Option[];
    registrationTypes: RegistrationTypeOption[];
    defaultInvitationMessage: string;
    defaultReminderMessage: string;
    defaultInvitationSubject: string;
    defaultReminderSubject: string;
};

const states: Record<
    EventState,
    { label: string; body: string; icon: LucideIcon; tone: string }
> = {
    published: {
        label: 'Live',
        body: 'Guests can open the invitation and reply.',
        icon: Globe,
        tone: 'bg-success-muted text-success',
    },
    draft: {
        label: 'Draft',
        body: 'Only you can see it. Publish when you’re ready to share.',
        icon: CircleDashed,
        tone: 'bg-warning-muted text-warning',
    },
    cancelled: {
        label: 'Cancelled',
        body: 'Guests can no longer register or reply.',
        icon: Ban,
        tone: 'bg-destructive-muted text-destructive',
    },
};

/**
 * The Settings tab, laid out like an app's settings: a list of sections
 * (sticky beside the panels from `lg` up, pills above them below), and
 * panels of label / control rows. Status, registration and the danger zone
 * sit among the event form's own sections.
 */
export default function EditEvent({
    event: { data: event },
    eventTypes,
    registrationTypes,
    defaultInvitationMessage,
    defaultReminderMessage,
    defaultInvitationSubject,
    defaultReminderSubject,
}: Props) {
    const sections: ExtraSection[] = [
        {
            id: 'status',
            title: 'Status',
            description: 'Whether guests can see the event.',
            icon: Activity,
            placement: 'start',
            content: <StatusRows event={event} />,
        },
        ...(event.registration_type !== 'guest_list_only'
            ? [
                  {
                      id: 'registration',
                      title: 'Registration',
                      description: 'The public page where people sign up.',
                      icon: Radio,
                      placement: 'access' as const,
                      content: (
                          <RegistrationRows
                              event={event}
                              registrationTypes={registrationTypes}
                          />
                      ),
                  },
              ]
            : []),
        {
            id: 'danger',
            title: 'Danger zone',
            description: 'Things that can’t be undone.',
            icon: ShieldAlert,
            placement: 'end',
            danger: true,
            content: <DangerRows event={event} />,
        },
    ];

    return (
        <EventLayout event={event}>
            <Head title={`Settings · ${event.title}`} />
            <PageHeader
                eyebrow={event.title}
                title="Settings"
                description="The event’s details, who can join, and publishing."
            />

            {event.state === 'cancelled' ? (
                <CancelledSettings event={event} sections={sections} />
            ) : (
                <EventForm
                    key={event.updated_at}
                    event={event}
                    eventTypes={eventTypes}
                    registrationTypes={registrationTypes}
                    defaultInvitationMessage={defaultInvitationMessage}
                    defaultReminderMessage={defaultReminderMessage}
                    defaultInvitationSubject={defaultInvitationSubject}
                    defaultReminderSubject={defaultReminderSubject}
                    mapPreviewUrl={(url) => mapPreview.url({ query: { url } })}
                    submitLabel="Save changes"
                    onSubmit={(form) =>
                        form.patch(update.url(event), { preserveScroll: true })
                    }
                    extraSections={sections}
                />
            )}
        </EventLayout>
    );
}

/** Live / Draft / Cancelled, and moving the event on (cancel asks first). */
function StatusRows({ event }: { event: Event }) {
    const current = states[event.state];
    const confirmation = useConfirm();

    const move = (next: EventState) => {
        const apply = () =>
            router.patch(
                state.url(event),
                { state: next },
                { preserveScroll: true },
            );

        if (next === 'cancelled') {
            confirmation.ask({
                title: 'Cancel this event? Guests can no longer reply. This can’t be undone.',
                confirmLabel: 'Cancel event',
                onConfirm: apply,
            });

            return;
        }

        apply();
    };

    return (
        <>
            <ConfirmBar
                request={confirmation.request}
                onCancel={confirmation.cancel}
            />
            <SettingRow label="Current status" hint={current.body}>
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={cn(
                            'inline-flex h-9 items-center gap-2 rounded-full px-3.5 text-sm font-bold',
                            current.tone,
                        )}
                    >
                        <current.icon className="size-4" />
                        {current.label}
                    </span>
                    {event.allowed_transitions.includes('published') && (
                        <button
                            type="button"
                            onClick={() => move('published')}
                            className="inline-flex h-9 items-center gap-1.5 rounded-full bg-primary px-4 text-sm font-bold text-primary-foreground"
                        >
                            <Rocket className="size-4" /> Publish
                        </button>
                    )}
                    {event.allowed_transitions.includes('draft') && (
                        <button
                            type="button"
                            onClick={() => move('draft')}
                            className="inline-flex h-9 items-center gap-1.5 rounded-full border border-input px-4 text-sm font-semibold hover:bg-raised"
                        >
                            <EyeOff className="size-4" /> Unpublish
                        </button>
                    )}
                </div>
            </SettingRow>
            {event.allowed_transitions.includes('cancelled') && (
                <SettingRow
                    label="Cancel the event"
                    hint="Guests see it’s cancelled and can no longer reply."
                >
                    <button
                        type="button"
                        onClick={() => move('cancelled')}
                        className="inline-flex h-9 items-center gap-1.5 rounded-full border border-destructive/30 px-4 text-sm font-semibold text-destructive hover:bg-destructive-muted"
                    >
                        <Ban className="size-4" /> Cancel event
                    </button>
                </SettingRow>
            )}
        </>
    );
}

/** Opening / closing public registration, and the public link. */
function RegistrationRows({
    event,
    registrationTypes,
}: {
    event: Event;
    registrationTypes: RegistrationTypeOption[];
}) {
    const [copied, setCopied] = useState(false);
    const option = registrationTypes.find(
        (type) => type.value === event.registration_type,
    );

    return (
        <>
            <SettingRow
                label="Open for registration"
                hint={
                    event.registration_open
                        ? event.state === 'published'
                            ? 'People can register on the public page.'
                            : 'People can register once the event is published.'
                        : 'Paused: nobody can register themselves.'
                }
                control={
                    <Switch
                        checked={event.registration_open}
                        onChange={(on) =>
                            event.state !== 'cancelled' &&
                            router.post(
                                (on ? open : close).url(event),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    />
                }
            />
            {event.public_url && (
                <SettingRow
                    label="Public link"
                    hint={`Opened ${event.public_link_open_count ?? 0} times.`}
                >
                    <div className="flex min-w-0 items-center gap-1.5 rounded-lg border border-input bg-raised p-1 pl-3">
                        <code className="min-w-0 flex-1 truncate text-xs">
                            {event.public_url.replace(/^https?:\/\//, '')}
                        </code>
                        <button
                            type="button"
                            onClick={() =>
                                void navigator.clipboard
                                    .writeText(event.public_url ?? '')
                                    .then(() => setCopied(true))
                            }
                            className="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md bg-card px-3 text-xs font-semibold shadow-card"
                        >
                            {copied ? (
                                <CircleCheck className="size-3.5 text-success" />
                            ) : (
                                <Copy className="size-3.5" />
                            )}
                            {copied ? 'Copied' : 'Copy'}
                        </button>
                    </div>
                </SettingRow>
            )}
            {option && (
                <SettingRow label={option.label} hint={option.description}>
                    <RegistrationTypeDetails option={option} />
                </SettingRow>
            )}
        </>
    );
}

/** Deleting the event (asks with the slider first). */
function DangerRows({ event }: { event: Event }) {
    const confirmation = useConfirm();

    return (
        <>
            <ConfirmBar
                request={confirmation.request}
                onCancel={confirmation.cancel}
            />
            <SettingRow
                label="Delete this event"
                hint="Removes the event and all its guests."
            >
                <button
                    type="button"
                    onClick={() =>
                        confirmation.ask({
                            title: `Delete "${event.title}" and all its guests?`,
                            confirmLabel: 'Delete',
                            onConfirm: () => router.delete(destroy.url(event)),
                        })
                    }
                    className="inline-flex h-9 items-center gap-1.5 rounded-full bg-destructive px-4 text-sm font-semibold text-white hover:bg-destructive/90"
                >
                    <Trash2 className="size-4" /> Delete event
                </button>
            </SettingRow>
        </>
    );
}

/** Cancelled events: the details, read-only, then the same side sections. */
function CancelledSettings({
    event,
    sections,
}: {
    event: Event;
    sections: ExtraSection[];
}) {
    const all: ExtraSection[] = [
        {
            id: 'details',
            title: 'Details',
            description: 'Cancelled events can no longer be edited.',
            icon: CalendarDays,
            placement: 'start',
            content: (
                <>
                    <SettingRow label="Description">
                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                            {event.description || 'No description.'}
                        </p>
                    </SettingRow>
                    <SettingRow label="Date">
                        <p className="text-sm">
                            {formatDate(event.event_date)}
                        </p>
                    </SettingRow>
                    <SettingRow label="Time">
                        <p className="text-sm">
                            {event.start_time
                                ? `${formatTime(event.start_time)}${event.end_time ? ` – ${formatTime(event.end_time)}` : ''}`
                                : '—'}
                        </p>
                    </SettingRow>
                    <SettingRow label="Venue">
                        <p className="text-sm">{event.location_name || '—'}</p>
                    </SettingRow>
                </>
            ),
        },
        ...sections,
    ];

    return (
        <div className="mx-auto grid max-w-4xl gap-10 pb-10">
            {all.map((section) => (
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
    );
}
