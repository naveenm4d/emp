import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, Receipt } from 'lucide-react';

import { PaymentRow } from '@/components/billing/payment-row';
import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import ClientLayout from '@/layouts/client-layout';
import { membership } from '@/routes/client';
import { show } from '@/routes/client/billing/payments';
import type { Paginated, Payment } from '@/types';

/** Every payment of the account, newest first. */
export default function BillingPayments({
    payments,
}: {
    payments: Paginated<Payment>;
}) {
    return (
        <ClientLayout>
            <Head title="Payments" />
            <PageHeader
                title="Payments"
                className="max-w-3xl"
                eyebrow={
                    <Link
                        href={membership.url()}
                        className="inline-flex items-center gap-0.5"
                    >
                        <ChevronLeft className="size-4" /> Membership
                    </Link>
                }
                description="Everything paid through PayHere, with receipts."
            />
            <div className="flex max-w-3xl flex-col gap-4 pt-1 pb-6">
                {payments.data.length === 0 ? (
                    <EmptyState
                        icon={Receipt}
                        title="No payments yet"
                        description="Plans, events, guests and designs you buy show up here."
                    />
                ) : (
                    <div className="divide-y divide-border overflow-hidden rounded-2xl bg-card shadow-card">
                        {payments.data.map((payment) => (
                            <PaymentRow
                                key={payment.id}
                                payment={payment}
                                href={show.url(payment)}
                            />
                        ))}
                    </div>
                )}
                <Pagination meta={payments.meta} />
            </div>
        </ClientLayout>
    );
}
