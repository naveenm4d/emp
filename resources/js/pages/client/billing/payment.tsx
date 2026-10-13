import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronLeft,
    Clock,
    Printer,
    XCircle,
} from 'lucide-react';

import { PageHeader } from '@/components/shared/page-header';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import ClientLayout from '@/layouts/client-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { membership } from '@/routes/client';
import { index as paymentsIndex } from '@/routes/client/billing/payments';
import type { Payment } from '@/types';

/** A payment PayHere processed: the receipt, or why it was declined. */
export default function BillingPayment({
    payment: wrapped,
}: {
    payment: { data: Payment };
}) {
    const payment = wrapped.data;

    return (
        <ClientLayout>
            <Head title={`Payment ${payment.order_id}`} />
            <PageHeader
                title={payment.status === 'failed' ? 'Payment' : 'Receipt'}
                className="max-w-2xl print:hidden"
                eyebrow={
                    <Link
                        href={membership.url()}
                        className="inline-flex items-center gap-0.5"
                    >
                        <ChevronLeft className="size-4" /> Membership
                    </Link>
                }
                actions={
                    payment.status === 'succeeded' && (
                        <Button
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer className="size-4" /> Print
                        </Button>
                    )
                }
            />

            <div className="flex max-w-2xl flex-col gap-4 pt-1 pb-6">
                <Outcome payment={payment} />

                <section className="rounded-3xl bg-card p-5 shadow-card">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <div className="text-xs font-semibold text-muted-foreground">
                                {payment.purpose_label}
                            </div>
                            <div className="text-base font-bold">
                                {payment.description}
                            </div>
                        </div>
                        <StatusBadge
                            status={payment.status}
                            label={payment.status_label}
                        />
                    </div>
                    <dl className="mt-5 grid grid-cols-1 gap-3 border-t border-border pt-4 text-sm sm:grid-cols-2">
                        {payment.purpose !== 'card_setup' && (
                            <Detail
                                label="Amount"
                                value={formatMoney(
                                    payment.amount,
                                    payment.currency,
                                )}
                            />
                        )}
                        <Detail label="Reference" value={payment.order_id} />
                        {payment.payhere_payment_id && (
                            <Detail
                                label="PayHere payment"
                                value={payment.payhere_payment_id}
                            />
                        )}
                        {payment.card && (
                            <Detail label="Card" value={payment.card} />
                        )}
                        <Detail
                            label={payment.paid_at ? 'Paid' : 'Started'}
                            value={formatDateTime(
                                payment.paid_at ?? payment.created_at,
                            )}
                        />
                        {payment.refunded_at && (
                            <Detail
                                label="Refunded"
                                value={formatDateTime(payment.refunded_at)}
                            />
                        )}
                    </dl>
                </section>

                <Link
                    href={paymentsIndex.url()}
                    className="px-1 text-sm font-semibold text-link print:hidden"
                >
                    All payments
                </Link>
            </div>
        </ClientLayout>
    );
}

function Outcome({ payment }: { payment: Payment }) {
    if (payment.status === 'succeeded') {
        return (
            <Banner tone="success" icon={CheckCircle2} title="Payment received">
                {payment.purpose === 'card_setup'
                    ? 'Your card is saved.'
                    : 'Thank you! It’s already applied to your account. A receipt is on its way by email.'}
            </Banner>
        );
    }

    return (
        <Banner
            tone="danger"
            icon={XCircle}
            title={
                payment.status === 'refunded'
                    ? 'Refunded'
                    : 'Payment didn’t go through'
            }
        >
            {payment.status_message ?? 'Nothing was applied to your account.'}
        </Banner>
    );
}

const tones = {
    success: 'bg-success-muted text-success',
    info: 'bg-info-muted text-info',
    warning: 'bg-warning-muted text-warning',
    danger: 'bg-destructive-muted text-destructive',
};

function Banner({
    tone,
    icon: Icon,
    title,
    children,
}: {
    tone: keyof typeof tones;
    icon: typeof Clock;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div
            className={`flex items-start gap-3 rounded-2xl px-4 py-3.5 motion-safe:animate-fade-in print:hidden ${tones[tone]}`}
        >
            <Icon className="mt-0.5 size-5 shrink-0" />
            <div>
                <div className="text-sm font-bold">{title}</div>
                <p className="text-sm text-foreground/80">{children}</p>
            </div>
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium break-all">{value}</dd>
        </div>
    );
}
