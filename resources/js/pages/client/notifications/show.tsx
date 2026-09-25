import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ClientLayout from '@/layouts/client-layout';
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/client/events/notifications';
import type { Event, Notification, Resource } from '@/types';

export default function ShowNotification({
    event: { data: event },
    notification: { data: n },
}: {
    event: Resource<Event>;
    notification: Resource<Notification>;
}) {
    return (
        <ClientLayout>
            <Head title="Message" />
            <PageHeader
                title={`Message to ${n.guest?.name ?? n.recipient}`}
                description={<StatusBadge status={n.status} />}
                actions={
                    <Link
                        href={index.url(event)}
                        className={buttonVariants({ variant: 'outline' })}
                    >
                        <ArrowLeft /> Back to messages
                    </Link>
                }
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Content</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="rounded-lg bg-muted p-4 text-sm whitespace-pre-line">
                            {n.message}
                        </p>
                        {n.error && (
                            <p className="mt-3 text-sm text-destructive">
                                {n.error}
                            </p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Delivery</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="space-y-2 text-sm">
                            <Row label="Channel" value={n.channel} />
                            <Row label="Recipient" value={n.recipient} />
                            <Row label="Attempts" value={String(n.attempts)} />
                            <Row
                                label="Sent"
                                value={formatDateTime(n.sent_at)}
                            />
                            <Row
                                label="Delivered"
                                value={formatDateTime(n.delivered_at)}
                            />
                        </dl>
                    </CardContent>
                </Card>
            </div>

            {n.deliveries && n.deliveries.length > 0 && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Provider log</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {n.deliveries.map((d) => (
                            <div
                                key={d.id}
                                className="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 text-sm last:border-0"
                            >
                                <span className="font-medium">{d.status}</span>
                                <span className="font-mono text-xs text-muted-foreground">
                                    {d.provider_id ?? '—'}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {formatDateTime(d.created_at)}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}
        </ClientLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="truncate font-medium capitalize">{value}</dd>
        </div>
    );
}
