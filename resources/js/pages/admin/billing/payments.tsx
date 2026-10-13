import { Head, Link, router } from '@inertiajs/react';
import { Receipt, Search } from 'lucide-react';
import { useState } from 'react';

import { RefundDialog } from '@/components/billing/refund-dialog';
import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
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
import { formatDateTime, formatMoney } from '@/lib/format';
import { useStaffCan } from '@/lib/permissions';
import { index } from '@/routes/admin/billing/payments';
import { index as pricesIndex } from '@/routes/admin/billing/prices';
import { show as showClient } from '@/routes/admin/clients';
import type { Option, Paginated, Payment } from '@/types';

type Filters = {
    search: string | null;
    status: string | null;
    purpose: string | null;
};

/** Every PayHere payment on the platform; refunds for staff allowed to. */
export default function BillingPayments({
    payments,
    filters,
    statuses,
    purposes,
    mockGateway,
}: {
    payments: Paginated<Payment>;
    filters: Filters;
    statuses: Option[];
    purposes: Option[];
    mockGateway: boolean;
}) {
    const can = useStaffCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [refunding, setRefunding] = useState<Payment | null>(null);

    const filter = (changes: Partial<Filters>) =>
        router.get(
            index.url(),
            { ...filters, ...changes },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <AdminLayout>
            <Head title="Payments" />
            <PageHeader
                title="Payments"
                description={
                    mockGateway
                        ? 'PayHere isn’t configured: payments go through the mock gateway.'
                        : 'Everything clients paid through PayHere.'
                }
                actions={
                    can('billing.prices') && (
                        <Button
                            variant="outline"
                            render={<Link href={pricesIndex.url()} />}
                        >
                            Prices
                        </Button>
                    )
                }
            />

            <div className="mb-4 flex flex-wrap gap-2">
                <form
                    className="relative"
                    onSubmit={(e) => {
                        e.preventDefault();
                        filter({ search: search || null });
                    }}
                >
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        className="w-64 pl-9"
                        placeholder="Order, PayHere ID, client"
                        aria-label="Search payments"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </form>
                <Select
                    className="w-40"
                    aria-label="Status"
                    value={filters.status ?? ''}
                    onChange={(e) => filter({ status: e.target.value || null })}
                >
                    <option value="">All statuses</option>
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </Select>
                <Select
                    className="w-44"
                    aria-label="What for"
                    value={filters.purpose ?? ''}
                    onChange={(e) =>
                        filter({ purpose: e.target.value || null })
                    }
                >
                    <option value="">Everything</option>
                    {purposes.map((purpose) => (
                        <option key={purpose.value} value={purpose.value}>
                            {purpose.label}
                        </option>
                    ))}
                </Select>
            </div>

            <div className="overflow-hidden rounded-lg bg-card shadow-card">
                {payments.data.length === 0 ? (
                    <EmptyState
                        icon={Receipt}
                        title="No payments"
                        description="Nothing matches these filters."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-4">Payment</TableHead>
                                <TableHead>Client</TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead className="pr-4 text-right" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payments.data.map((payment) => (
                                <TableRow key={payment.id}>
                                    <TableCell className="pl-4">
                                        <p className="font-medium">
                                            {payment.description}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {payment.order_id}
                                            {payment.payhere_payment_id &&
                                                ` · PayHere ${payment.payhere_payment_id}`}
                                            {payment.card &&
                                                ` · ${payment.card}`}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        {payment.client && (
                                            <button
                                                type="button"
                                                className="text-left hover:underline"
                                                onClick={() =>
                                                    router.visit(
                                                        showClient.url(
                                                            payment.client!.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                <p>{payment.client.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {payment.client.email}
                                                </p>
                                            </button>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {payment.purpose === 'card_setup'
                                            ? '—'
                                            : formatMoney(
                                                  payment.amount,
                                                  payment.currency,
                                              )}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={payment.status}
                                            label={payment.status_label}
                                        />
                                        {payment.status === 'succeeded' &&
                                            !payment.fulfilled && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    Not applied yet
                                                </p>
                                            )}
                                        {payment.status_message &&
                                            payment.status !== 'succeeded' && (
                                                <p
                                                    className="mt-1 max-w-48 truncate text-xs text-muted-foreground"
                                                    title={
                                                        payment.status_message
                                                    }
                                                >
                                                    {payment.status_message}
                                                </p>
                                            )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDateTime(
                                            payment.paid_at ??
                                                payment.created_at,
                                        )}
                                    </TableCell>
                                    <TableCell className="pr-4 text-right">
                                        {can('billing.refund') &&
                                            payment.status === 'succeeded' &&
                                            payment.purpose !==
                                                'card_setup' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setRefunding(payment)
                                                    }
                                                >
                                                    Refund
                                                </Button>
                                            )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination meta={payments.meta} />
            </div>

            {refunding && (
                <RefundDialog
                    payment={refunding}
                    onClose={() => setRefunding(null)}
                />
            )}
        </AdminLayout>
    );
}
