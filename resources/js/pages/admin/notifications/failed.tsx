import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, RotateCw } from 'lucide-react';

import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { formatDateTime } from '@/lib/format';
import { retry } from '@/routes/admin/notifications';
import type { Notification, Paginated } from '@/types';

export default function FailedNotifications({
    notifications,
}: {
    notifications: Paginated<Notification>;
}) {
    const canRetry = usePage().props.auth.staff?.permissions.includes(
        'notifications.retry',
    );

    return (
        <AdminLayout>
            <Head title="Failed messages" />
            <PageHeader
                title="Failed messages"
                description="Messages that could not be delivered after automatic retries."
            />

            <div className="overflow-hidden rounded-lg bg-card shadow-card">
                {notifications.data.length === 0 ? (
                    <EmptyState
                        icon={CheckCircle2}
                        title="No failed messages"
                        description="Every message was delivered to its provider."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-4">
                                    Recipient
                                </TableHead>
                                <TableHead>Event / client</TableHead>
                                <TableHead>Error</TableHead>
                                <TableHead>Attempts</TableHead>
                                <TableHead>Last attempt</TableHead>
                                <TableHead className="pr-4 text-right" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {notifications.data.map((n) => (
                                <TableRow key={n.id}>
                                    <TableCell className="pl-4">
                                        <p className="font-medium">
                                            {n.guest?.name ?? '—'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {n.channel} · {n.recipient}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <p>{n.event?.title}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {n.event?.client?.name}
                                        </p>
                                    </TableCell>
                                    <TableCell
                                        className="max-w-xs truncate text-xs text-destructive"
                                        title={n.error ?? ''}
                                    >
                                        {n.error}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {n.attempts}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(n.updated_at)}
                                    </TableCell>
                                    <TableCell className="pr-4 text-right">
                                        {canRetry && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        retry.url(n),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <RotateCw /> Retry
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination meta={notifications.meta} />
            </div>
        </AdminLayout>
    );
}
