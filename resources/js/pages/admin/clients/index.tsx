import { Head, Link, router } from '@inertiajs/react';
import { Building2 } from 'lucide-react';

import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { formatDate } from '@/lib/format';
import { index, show } from '@/routes/admin/clients';
import type { Client, Paginated } from '@/types';

export default function ClientsIndex({
    clients,
    filters,
}: {
    clients: Paginated<Client>;
    filters: { search: string | null };
}) {
    return (
        <AdminLayout>
            <Head title="Clients" />
            <PageHeader
                title="Clients"
                description="Subscribed customers who create events on EMP."
            />

            <Input
                className="mb-4 max-w-xs"
                placeholder="Search name or email…"
                defaultValue={filters.search ?? ''}
                onKeyDown={(e) =>
                    e.key === 'Enter' &&
                    router.get(
                        index.url(),
                        { search: e.currentTarget.value || null },
                        { preserveState: true, replace: true },
                    )
                }
            />

            <div className="overflow-hidden rounded-xl border border-border bg-card">
                {clients.data.length === 0 ? (
                    <EmptyState icon={Building2} title="No clients found" />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-4">Client</TableHead>
                                <TableHead>Events</TableHead>
                                <TableHead>Email verified</TableHead>
                                <TableHead className="pr-4">Joined</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {clients.data.map((client) => (
                                <TableRow
                                    key={client.id}
                                    className="cursor-pointer"
                                    onClick={() =>
                                        router.visit(show.url(client))
                                    }
                                >
                                    <TableCell className="pl-4">
                                        <Link
                                            href={show.url(client)}
                                            className="font-medium hover:underline"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            {client.name}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            {client.email}
                                        </p>
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {client.events_count}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {client.email_verified_at
                                            ? 'Yes'
                                            : 'No'}
                                    </TableCell>
                                    <TableCell className="pr-4 text-muted-foreground">
                                        {formatDate(client.created_at)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination meta={clients.meta} />
            </div>
        </AdminLayout>
    );
}
