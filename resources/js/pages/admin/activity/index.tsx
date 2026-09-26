import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, History } from 'lucide-react';
import { useState } from 'react';

import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { Select } from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/activity';
import { show as showClient } from '@/routes/admin/clients';
import type { Option, Paginated, StaffActivity } from '@/types';

type Filters = {
    staff: string | null;
    client: string | null;
    action: string | null;
};

/**
 * The activity log: everything staff did in the admin console, newest first.
 * Only staff with `activity.read` get here.
 */
export default function ActivityIndex({
    activities,
    filters,
    staff,
    actions,
}: {
    activities: Paginated<StaffActivity>;
    filters: Filters;
    staff: Option[];
    actions: Option[];
}) {
    const filter = (changes: Partial<Filters>) =>
        router.get(
            index.url(),
            { ...filters, ...changes },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <AdminLayout>
            <Head title="Activity" />
            <PageHeader
                title="Activity"
                description="Everything staff did in the admin console."
            />

            <div className="mb-4 flex flex-wrap gap-2">
                <Select
                    className="w-48"
                    aria-label="Staff member"
                    value={filters.staff ?? ''}
                    onChange={(e) => filter({ staff: e.target.value || null })}
                >
                    <option value="">All staff</option>
                    {staff.map((member) => (
                        <option key={member.value} value={member.value}>
                            {member.label}
                        </option>
                    ))}
                </Select>
                <Select
                    className="w-64"
                    aria-label="Action"
                    value={filters.action ?? ''}
                    onChange={(e) => filter({ action: e.target.value || null })}
                >
                    <option value="">All actions</option>
                    {actions.map((action) => (
                        <option key={action.value} value={action.value}>
                            {action.label}
                        </option>
                    ))}
                </Select>
                {filters.client && (
                    <button
                        type="button"
                        className="text-sm text-primary hover:underline"
                        onClick={() => filter({ client: null })}
                    >
                        Showing one client · show all
                    </button>
                )}
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-card">
                {activities.data.length === 0 ? (
                    <EmptyState
                        icon={History}
                        title="Nothing logged yet"
                        description="Changes staff make in the admin console show up here."
                    />
                ) : (
                    <ol className="divide-y">
                        {activities.data.map((activity) => (
                            <ActivityRow
                                key={activity.id}
                                activity={activity}
                                onClient={(id) => filter({ client: id })}
                            />
                        ))}
                    </ol>
                )}
                <Pagination meta={activities.meta} />
            </div>
        </AdminLayout>
    );
}

function ActivityRow({
    activity,
    onClient,
}: {
    activity: StaffActivity;
    onClient: (clientId: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const hasChanges =
        activity.changes !== null && Object.keys(activity.changes).length > 0;

    return (
        <li className="px-4 py-3 text-sm">
            <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                <div className="min-w-0">
                    <p className="font-medium">{activity.description}</p>
                    <p className="text-xs text-muted-foreground">
                        {activity.staff?.name ?? 'Deleted staff member'}
                        {activity.client && (
                            <>
                                {' · '}
                                <Link
                                    href={showClient.url(activity.client.id)}
                                    className="hover:underline"
                                >
                                    {activity.client.name}
                                </Link>{' '}
                                <button
                                    type="button"
                                    className="text-primary hover:underline"
                                    onClick={() =>
                                        activity.client &&
                                        onClient(activity.client.id)
                                    }
                                >
                                    (filter)
                                </button>
                            </>
                        )}
                        {' · '}
                        <code className="text-[11px]">{activity.action}</code>
                    </p>
                    {activity.note && (
                        <p className="mt-1 rounded-md bg-muted/60 px-2 py-1 text-xs">
                            Note: {activity.note}
                        </p>
                    )}
                </div>
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <time dateTime={activity.created_at}>
                        {formatDateTime(activity.created_at)}
                    </time>
                    {hasChanges && (
                        <button
                            type="button"
                            aria-expanded={open}
                            onClick={() => setOpen(!open)}
                            className="inline-flex items-center gap-0.5 hover:text-foreground"
                        >
                            Details
                            <ChevronDown
                                className={cn(
                                    'size-3.5 transition-transform',
                                    open && 'rotate-180',
                                )}
                            />
                        </button>
                    )}
                </div>
            </div>
            {open && hasChanges && (
                <pre className="mt-2 max-h-64 overflow-auto rounded-md bg-muted/60 p-2 text-[11px] leading-relaxed">
                    {JSON.stringify(activity.changes, null, 2)}
                </pre>
            )}
        </li>
    );
}
