import { Head, router } from '@inertiajs/react';
import { Copy, Mail } from 'lucide-react';

import { EventTabs } from '@/components/events/event-tabs';
import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import ClientLayout from '@/layouts/client-layout';
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/client/events/invitations';
import { expire, resend, send } from '@/routes/client/invitations';
import type {
    Event,
    Invitation,
    InvitationSummary,
    Option,
    Paginated,
    Resource,
} from '@/types';

type Props = {
    event: Resource<Event>;
    invitations: Paginated<Invitation>;
    summary: InvitationSummary;
    filters: { status: string | null };
    statuses: Option[];
};

export default function InvitationsIndex({
    event: { data: event },
    invitations,
    summary,
    filters,
    statuses,
}: Props) {
    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });

    return (
        <ClientLayout>
            <Head title={`Invitations · ${event.title}`} />
            <PageHeader
                title={event.title}
                description={`${summary.total} invitations · ${summary.sent} awaiting reply · ${summary.accepted} accepted · ${summary.declined} declined · ${summary.expired} expired`}
            />
            <EventTabs event={event} />

            <div className="mb-4">
                <Select
                    className="w-44"
                    value={filters.status ?? ''}
                    onChange={(e) =>
                        router.get(
                            index.url(event),
                            { status: e.target.value || null },
                            { preserveState: true, replace: true },
                        )
                    }
                >
                    <option value="">All statuses</option>
                    {statuses.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </Select>
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-card">
                {invitations.data.length === 0 ? (
                    <EmptyState
                        icon={Mail}
                        title="No invitations yet"
                        description="Invite approved guests from the Guests tab."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-4">Guest</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Sent</TableHead>
                                <TableHead>Expires</TableHead>
                                <TableHead>Responded</TableHead>
                                <TableHead className="pr-4 text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invitations.data.map((invitation) => (
                                <TableRow key={invitation.id}>
                                    <TableCell className="pl-4">
                                        <p className="font-medium">
                                            {invitation.guest?.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {invitation.guest?.phone ??
                                                'No phone'}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={
                                                invitation.is_expired &&
                                                invitation.status === 'sent'
                                                    ? 'expired'
                                                    : invitation.status
                                            }
                                        />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(invitation.sent_at)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(invitation.expires_at)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(
                                            invitation.responded_at,
                                        )}
                                    </TableCell>
                                    <TableCell className="pr-4">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                size="icon-sm"
                                                variant="ghost"
                                                title="Copy RSVP link"
                                                onClick={() =>
                                                    navigator.clipboard.writeText(
                                                        invitation.rsvp_url,
                                                    )
                                                }
                                            >
                                                <Copy />
                                            </Button>
                                            {invitation.status ===
                                                'pending' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        post(
                                                            send.url(
                                                                invitation,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Send
                                                </Button>
                                            )}
                                            {invitation.status === 'sent' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        post(
                                                            resend.url(
                                                                invitation,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Resend
                                                </Button>
                                            )}
                                            {(invitation.status === 'pending' ||
                                                invitation.status ===
                                                    'sent') && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        post(
                                                            expire.url(
                                                                invitation,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    Expire
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination meta={invitations.meta} />
            </div>
        </ClientLayout>
    );
}
