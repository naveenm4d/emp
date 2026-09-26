import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CalendarDays,
    Mail,
    MessageSquare,
    Users,
} from 'lucide-react';

import { MetricCard } from '@/components/shared/metric-card';
import { PageHeader } from '@/components/shared/page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { failed } from '@/routes/admin/notifications';
import type { PlatformStats } from '@/types';

export default function AdminDashboard({
    stats,
}: {
    stats: PlatformStats | null;
}) {
    const { auth } = usePage().props;

    return (
        <AdminLayout>
            <Head title="Dashboard" />
            <PageHeader
                title={`Welcome, ${auth.staff?.name.split(' ')[0]}`}
                description="Platform overview."
            />

            {!stats ? (
                <p className="text-sm text-muted-foreground">
                    You do not have access to platform statistics.
                </p>
            ) : (
                <>
                    <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <MetricCard
                            title="Clients"
                            value={stats.clients}
                            icon={Building2}
                        />
                        <MetricCard
                            title="Events"
                            value={stats.events}
                            icon={CalendarDays}
                            description={`${stats.eventsByState.published} published`}
                        />
                        <MetricCard
                            title="Guests"
                            value={stats.guests}
                            icon={Users}
                        />
                        <MetricCard
                            title="RSVP links"
                            value={stats.rsvps}
                            icon={Mail}
                        />
                        <MetricCard
                            title="Messages"
                            value={stats.notifications}
                            icon={MessageSquare}
                        />
                        <MetricCard
                            title="Failed messages"
                            value={stats.failedNotifications}
                            icon={AlertTriangle}
                            tone={
                                stats.failedNotifications > 0
                                    ? 'danger'
                                    : 'default'
                            }
                            description={
                                stats.failedNotifications > 0
                                    ? 'Needs attention'
                                    : 'All clear'
                            }
                        />
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Events by state</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {Object.entries(stats.eventsByState).map(
                                    ([state, count]) => (
                                        <div key={state}>
                                            <div className="mb-1 flex justify-between text-sm">
                                                <span className="capitalize">
                                                    {state}
                                                </span>
                                                <span className="text-muted-foreground tabular-nums">
                                                    {count}
                                                </span>
                                            </div>
                                            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-primary"
                                                    style={{
                                                        width: `${stats.events ? (count / stats.events) * 100 : 0}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    ),
                                )}
                            </CardContent>
                        </Card>
                        {stats.failedNotifications > 0 &&
                            auth.staff?.permissions.includes(
                                'notifications.read',
                            ) && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Delivery problems</CardTitle>
                                    </CardHeader>
                                    <CardContent className="text-sm text-muted-foreground">
                                        {stats.failedNotifications} message(s)
                                        could not be delivered after automatic
                                        retries.{' '}
                                        <Link
                                            href={failed.url()}
                                            className="text-primary hover:underline"
                                        >
                                            Review failed messages
                                        </Link>
                                    </CardContent>
                                </Card>
                            )}
                    </div>
                </>
            )}
        </AdminLayout>
    );
}
