import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    BellRing,
    ChevronRight,
    MessageSquare,
    Send,
} from 'lucide-react';

import { channelIcons, statusStyles } from '@/components/messages/message-meta';
import { MessagesPreview } from '@/components/plans/feature-previews';
import { LockedEventPage } from '@/components/plans/locked-feature';
import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import EventLayout from '@/layouts/event-layout';
import { initials, timeAgo } from '@/lib/format';
import { renderWhatsApp } from '@/lib/message-format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/client/events/notifications';
import { show } from '@/routes/client/notifications';
import type {
    Event,
    Notification,
    NotificationStatus,
    Paginated,
    Resource,
} from '@/types';

type StatusCounts = Record<NotificationStatus | 'all', number>;

type Props = {
    event: Resource<Event>;
    locked?: undefined;
    notifications: Paginated<Notification>;
    statusCounts: StatusCounts;
    /** The status the list is narrowed to; null = all. */
    status: NotificationStatus | null;
};

/** Not in the client's plan: the controller sends only the event. */
type LockedProps = { event: Resource<Event>; locked: true };

export default function NotificationsIndex(props: Props | LockedProps) {
    if (props.locked) {
        return (
            <LockedEventPage
                event={props.event.data}
                title="Messages"
                feature="message_log"
                preview={<MessagesPreview />}
            />
        );
    }

    return <MessagesPage {...props} />;
}

const FILTERS: (NotificationStatus | 'all')[] = [
    'all',
    'sent',
    'delivered',
    'read',
    'failed',
    'pending',
];

/**
 * The event's Messages tab: status filters with counts, then every message
 * as a card (who, which message, a preview, where it got to).
 */
function MessagesPage({
    event: { data: event },
    notifications,
    statusCounts,
    status,
}: Props) {
    const filter = (next: NotificationStatus | 'all') =>
        router.get(index.url(event), next === 'all' ? {} : { status: next }, {
            preserveScroll: true,
            preserveState: true,
        });

    return (
        <EventLayout event={event}>
            <Head title={`Messages · ${event.title}`} />
            <PageHeader
                eyebrow={event.title}
                title="Messages"
                description="Every invitation and reminder sent to your guests, and where it got to."
            />

            <div
                role="tablist"
                aria-label="Filter by status"
                className="mb-4 flex scrollbar-none gap-2 overflow-x-auto pb-1"
            >
                {FILTERS.filter(
                    (item) =>
                        item === 'all' ||
                        item !== 'pending' ||
                        statusCounts.pending > 0,
                ).map((item) => {
                    const selected = (status ?? 'all') === item;
                    const style = item === 'all' ? null : statusStyles[item];

                    return (
                        <button
                            key={item}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            onClick={() => filter(item)}
                            className={cn(
                                'inline-flex h-10 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-semibold transition-colors',
                                selected
                                    ? 'border-transparent bg-strong text-strong-foreground'
                                    : 'border-input bg-card text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {style ? (
                                <style.icon className="size-4" />
                            ) : (
                                <MessageSquare className="size-4" />
                            )}
                            {style ? style.label : 'All'}
                            <span
                                className={cn(
                                    'rounded-full px-1.5 text-xs tabular-nums',
                                    selected
                                        ? 'bg-strong-foreground/15'
                                        : 'bg-foreground/6',
                                )}
                            >
                                {statusCounts[item]}
                            </span>
                        </button>
                    );
                })}
            </div>

            {notifications.data.length === 0 ? (
                <div className="rounded-2xl bg-card shadow-card">
                    <EmptyState
                        icon={MessageSquare}
                        title={status ? 'Nothing here' : 'No messages yet'}
                        description={
                            status
                                ? 'No messages with this status.'
                                : 'Messages appear here once you send RSVP links.'
                        }
                    />
                </div>
            ) : (
                <ul className="grid gap-3">
                    {notifications.data.map((notification) => (
                        <MessageCard
                            key={notification.id}
                            notification={notification}
                        />
                    ))}
                </ul>
            )}
            <Pagination meta={notifications.meta} />
        </EventLayout>
    );
}

function MessageCard({ notification: n }: { notification: Notification }) {
    const style = statusStyles[n.status];
    const Channel = channelIcons[n.channel];
    const name = n.guest?.name ?? 'Removed guest';

    return (
        <li>
            <Link
                href={show.url(n)}
                className="group flex gap-3.5 rounded-2xl bg-card p-4 shadow-card transition-colors hover:bg-raised/60 sm:p-5"
            >
                <span className="relative shrink-0">
                    <span className="flex size-11 items-center justify-center rounded-full bg-foreground/6 text-sm font-bold">
                        {initials(name)}
                    </span>
                    <span
                        className={cn(
                            'absolute -right-1 -bottom-1 flex size-5 items-center justify-center rounded-full ring-2 ring-card',
                            n.channel === 'whatsapp'
                                ? 'bg-[#25d366] text-white'
                                : 'bg-strong text-strong-foreground',
                        )}
                    >
                        <Channel className="size-3" />
                    </span>
                </span>

                <span className="min-w-0 flex-1">
                    <span className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="truncate font-semibold">{name}</span>
                        {n.kind && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-foreground/6 px-2 py-0.5 text-[11px] font-semibold text-muted-foreground">
                                {n.kind === 'rsvp_reminder' ? (
                                    <BellRing className="size-3" />
                                ) : (
                                    <Send className="size-3" />
                                )}
                                {n.kind === 'rsvp_reminder'
                                    ? 'Reminder'
                                    : 'Invitation'}
                            </span>
                        )}
                        <span className="ml-auto text-xs text-subtle">
                            {timeAgo(n.sent_at ?? n.created_at)}
                        </span>
                    </span>
                    <span className="mt-1.5 line-clamp-2 block text-sm text-muted-foreground">
                        {renderWhatsApp(n.message.split('\n')[0])}
                    </span>
                    {n.status === 'failed' && n.error && (
                        <span className="mt-2 flex items-start gap-1.5 rounded-lg bg-destructive-muted px-2.5 py-1.5 text-xs text-destructive">
                            <AlertTriangle className="mt-px size-3.5 shrink-0" />
                            <span className="line-clamp-2">{n.error}</span>
                        </span>
                    )}
                    <span className="mt-2.5 flex flex-wrap items-center gap-2">
                        <span
                            className={cn(
                                'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                style.tone,
                            )}
                        >
                            <style.icon className="size-3.5" />
                            {style.label}
                        </span>
                        <span className="truncate text-xs text-subtle">
                            {n.recipient}
                        </span>
                    </span>
                </span>

                <ChevronRight className="mt-3 size-4 shrink-0 text-subtle transition-transform group-hover:translate-x-0.5" />
            </Link>
        </li>
    );
}
