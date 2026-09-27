import { Head, router } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';

import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { StatusBadge } from '@/components/shared/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { MessagesPreview } from '@/components/plans/feature-previews';
import { LockedEventPage } from '@/components/plans/locked-feature';
import EventLayout from '@/layouts/event-layout';
import { formatDateTime } from '@/lib/format';
import { show } from '@/routes/client/notifications';
import type { Event, Notification, Paginated, Resource } from '@/types';

type Props = {
    event: Resource<Event>;
    locked?: undefined;
    notifications: Paginated<Notification>;
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

function MessagesPage({ event: { data: event }, notifications }: Props) {
    return (
        <EventLayout event={event}>
            <Head title={`Messages · ${event.title}`} />
            <PageHeader
                eyebrow={event.title}
                title="Messages"
                description="Messages sent to your guests."
            />

            <div className="overflow-hidden rounded-lg bg-card shadow-card">
                {notifications.data.length === 0 ? (
                    <EmptyState
                        icon={MessageSquare}
                        title="No messages yet"
                        description="Messages appear here once you send RSVP links."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-4">Guest</TableHead>
                                <TableHead>Channel</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Sent</TableHead>
                                <TableHead className="pr-4">
                                    Delivered
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {notifications.data.map((n) => (
                                <TableRow
                                    key={n.id}
                                    className="cursor-pointer"
                                    onClick={() => router.visit(show.url(n))}
                                >
                                    <TableCell className="pl-4">
                                        <p className="font-medium">
                                            {n.guest?.name ?? 'Removed guest'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {n.recipient}
                                        </p>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground capitalize">
                                        {n.channel}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={n.status} />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(n.sent_at)}
                                    </TableCell>
                                    <TableCell className="pr-4 text-muted-foreground">
                                        {formatDateTime(n.delivered_at)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination meta={notifications.meta} />
            </div>
        </EventLayout>
    );
}
