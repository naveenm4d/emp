import { Head, Link, usePoll } from '@inertiajs/react';
import { ChevronLeft, Clock, XCircle } from 'lucide-react';

import { PageHeader } from '@/components/shared/page-header';
import ClientLayout from '@/layouts/client-layout';
import { formatMoney } from '@/lib/format';
import { membership } from '@/routes/client';
import type { Checkout } from '@/types';

/**
 * Where PayHere sends the payer back. Nothing is recorded until PayHere's
 * notification processes the order: while it waits this page checks again,
 * and the server moves on to the payment's receipt once it exists.
 */
export default function BillingCheckout({
    checkout: wrapped,
    canceled,
}: {
    /** null: no longer waiting (canceled, or never finished). */
    checkout: { data: Checkout } | null;
    canceled: boolean;
}) {
    const checkout = wrapped?.data ?? null;
    const waiting = checkout !== null && !canceled;

    usePoll(3000, {}, { autoStart: waiting });

    return (
        <ClientLayout>
            <Head title="Payment" />
            <PageHeader
                title="Payment"
                className="max-w-2xl"
                eyebrow={
                    <Link
                        href={membership.url()}
                        className="inline-flex items-center gap-0.5"
                    >
                        <ChevronLeft className="size-4" /> Membership
                    </Link>
                }
            />
            <div className="flex max-w-2xl flex-col gap-4 pt-1 pb-6">
                {waiting ? (
                    <div className="flex items-start gap-3 rounded-2xl bg-info-muted px-4 py-3.5 text-info motion-safe:animate-fade-in">
                        <Clock className="mt-0.5 size-5 shrink-0" />
                        <div>
                            <div className="text-sm font-bold">
                                Waiting for PayHere…
                            </div>
                            <p className="text-sm text-foreground/80">
                                This usually takes a few seconds. You can leave
                                this page; the payment appears under Membership
                                as soon as PayHere confirms it.
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="flex items-start gap-3 rounded-2xl bg-warning-muted px-4 py-3.5 text-warning motion-safe:animate-fade-in">
                        <XCircle className="mt-0.5 size-5 shrink-0" />
                        <div>
                            <div className="text-sm font-bold">
                                Payment not completed
                            </div>
                            <p className="text-sm text-foreground/80">
                                Nothing was charged. Start again from Membership
                                whenever you’re ready.
                            </p>
                        </div>
                    </div>
                )}

                {checkout && (
                    <section className="rounded-3xl bg-card p-5 shadow-card">
                        <div className="text-xs font-semibold text-muted-foreground">
                            {checkout.purpose_label}
                        </div>
                        <div className="text-base font-bold">
                            {checkout.description}
                        </div>
                        <div className="mt-3 flex items-baseline justify-between border-t border-border pt-3 text-sm">
                            <span className="text-muted-foreground">
                                {checkout.order_id}
                            </span>
                            {checkout.purpose !== 'card_setup' && (
                                <span className="font-bold tabular-nums">
                                    {formatMoney(
                                        checkout.amount,
                                        checkout.currency,
                                    )}
                                </span>
                            )}
                        </div>
                    </section>
                )}
            </div>
        </ClientLayout>
    );
}
