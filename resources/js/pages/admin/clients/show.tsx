import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, Plus } from 'lucide-react';

import { PlanCard } from '@/components/clients/plan-card';
import { EmptyState } from '@/components/shared/empty-state';
import { FormField } from '@/components/shared/form-field';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
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
import { useStaffCan } from '@/lib/permissions';
import { show, update } from '@/routes/admin/clients';
import { create, edit } from '@/routes/admin/clients/events';
import type {
    Client,
    ClientPlanUsage,
    Event,
    Option,
    Paginated,
    Resource,
    StaffActivity,
} from '@/types';

type Filters = { state: string | null; search: string | null };

export default function ShowClient({
    client: { data: client },
    events,
    filters,
    states,
    plan,
    plans,
    planHistory,
}: {
    client: Resource<Client>;
    events: Paginated<Event>;
    filters: Filters;
    states: Option[];
    plan: ClientPlanUsage;
    plans: Option[];
    planHistory: StaffActivity[] | null;
}) {
    const can = useStaffCan();

    const filter = (changes: Partial<Filters>) =>
        router.get(
            show.url(client),
            { ...filters, ...changes },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <AdminLayout>
            <Head title={client.name} />
            <PageHeader
                title={client.name}
                description={client.email}
                actions={
                    can('events.create') && (
                        <Link
                            href={create.url(client)}
                            className={buttonVariants()}
                        >
                            <Plus /> New event
                        </Link>
                    )
                }
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    <div className="flex flex-wrap gap-2">
                        <Input
                            className="max-w-xs"
                            placeholder="Search title or slug…"
                            defaultValue={filters.search ?? ''}
                            onKeyDown={(e) =>
                                e.key === 'Enter' &&
                                filter({
                                    search: e.currentTarget.value || null,
                                })
                            }
                        />
                        <Select
                            className="w-40"
                            value={filters.state ?? ''}
                            onChange={(e) =>
                                filter({ state: e.target.value || null })
                            }
                        >
                            <option value="">All states</option>
                            {states.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </Select>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-border bg-card">
                        {events.data.length === 0 ? (
                            <EmptyState
                                icon={CalendarDays}
                                title="No events yet"
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-4">
                                            Event
                                        </TableHead>
                                        <TableHead>State</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Registration</TableHead>
                                        <TableHead className="pr-4 text-right">
                                            Guests
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {events.data.map((event) => (
                                        <TableRow key={event.id}>
                                            <TableCell className="pl-4">
                                                {can('events.update') ? (
                                                    <Link
                                                        href={edit.url({
                                                            client: client.id,
                                                            event: event.id,
                                                        })}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {event.title}
                                                    </Link>
                                                ) : (
                                                    <p className="font-medium">
                                                        {event.title}
                                                    </p>
                                                )}
                                                <p className="text-xs text-muted-foreground">
                                                    /{event.slug}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={event.state}
                                                />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatDate(event.event_date)}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {event.registration_type ===
                                                'guest_list_only'
                                                    ? 'Guest list only'
                                                    : event.registration_open
                                                      ? 'Open'
                                                      : 'Closed'}
                                            </TableCell>
                                            <TableCell className="pr-4 text-right tabular-nums">
                                                {event.guests_count}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <Pagination meta={events.meta} />
                    </div>
                </div>

                <div className="space-y-6">
                    <PlanCard
                        client={client}
                        usage={plan}
                        plans={plans}
                        history={planHistory}
                        canEdit={can('clients.plan')}
                    />
                    {can('clients.update') ? (
                        <EditClientCard client={client} />
                    ) : (
                        <ClientDetailsCard client={client} />
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

function EditClientCard({ client }: { client: Client }) {
    const form = useForm({
        name: client.name,
        email: client.email,
        password: '',
        password_confirmation: '',
    });

    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>Client details</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(update.url(client), {
                            preserveScroll: true,
                            onSuccess: () =>
                                form.reset('password', 'password_confirmation'),
                        });
                    }}
                >
                    <FormField
                        label="Name"
                        htmlFor="name"
                        error={form.errors.name}
                    >
                        <Input
                            id="name"
                            value={form.data.name}
                            aria-invalid={!!form.errors.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        label="Email"
                        htmlFor="email"
                        error={form.errors.email}
                        hint="Changing it marks the email as unverified."
                    >
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            aria-invalid={!!form.errors.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        label="New password"
                        htmlFor="password"
                        error={form.errors.password}
                        hint="Leave empty to keep the current password."
                    >
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            aria-invalid={!!form.errors.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        label="Confirm new password"
                        htmlFor="password_confirmation"
                    >
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(e) =>
                                form.setData(
                                    'password_confirmation',
                                    e.target.value,
                                )
                            }
                        />
                    </FormField>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        Save client
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function ClientDetailsCard({ client }: { client: Client }) {
    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>Client details</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="space-y-3 text-sm">
                    <div>
                        <dt className="text-muted-foreground">Email</dt>
                        <dd>{client.email}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Email verified
                        </dt>
                        <dd>{client.email_verified_at ? 'Yes' : 'No'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Joined</dt>
                        <dd>{formatDate(client.created_at ?? null)}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}
