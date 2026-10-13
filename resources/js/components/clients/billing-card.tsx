import { useState } from 'react';

import { PaymentRow } from '@/components/billing/payment-row';
import { RefundDialog } from '@/components/billing/refund-dialog';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { useStaffCan } from '@/lib/permissions';
import type { Payment, PaymentCard, Subscription } from '@/types';

export type ClientBilling = {
    subscription: Subscription | null;
    cards: PaymentCard[];
    payments: Payment[];
};

/** Staff: the client's PayHere subscription, saved cards (masked) and latest payments. */
export function BillingCard({ billing }: { billing: ClientBilling }) {
    const can = useStaffCan();
    const [refunding, setRefunding] = useState<Payment | null>(null);
    const { subscription } = billing;
    const cards = billing.cards.filter((card) => !card.removed);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Billing</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
                <div>
                    <div className="mb-1 text-xs font-semibold text-muted-foreground">
                        Subscription
                    </div>
                    {subscription ? (
                        <div className="flex items-start justify-between gap-2">
                            <span>
                                Business, {subscription.interval}
                                <span className="block text-xs text-muted-foreground">
                                    {subscription.auto_renew
                                        ? 'Renews'
                                        : 'Ends'}{' '}
                                    {formatDate(
                                        subscription.current_period_end,
                                    )}
                                    {subscription.next_interval &&
                                        ` · then ${subscription.next_interval}`}
                                </span>
                            </span>
                            <StatusBadge
                                status={subscription.status}
                                label={subscription.status_label}
                            />
                        </div>
                    ) : (
                        <p className="text-muted-foreground">None</p>
                    )}
                </div>
                <div>
                    <div className="mb-1 text-xs font-semibold text-muted-foreground">
                        Saved cards
                    </div>
                    {cards.length === 0 ? (
                        <p className="text-muted-foreground">None</p>
                    ) : (
                        <ul className="space-y-0.5">
                            {cards.map((card) => (
                                <li key={card.id}>
                                    {card.label}
                                    <span className="text-xs text-muted-foreground">
                                        {card.expiry && ` · ${card.expiry}`}
                                        {card.is_default && ' · default'}
                                        {card.is_expired && ' · expired'}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <div>
                    <div className="mb-1 text-xs font-semibold text-muted-foreground">
                        Latest payments
                    </div>
                    {billing.payments.length === 0 ? (
                        <p className="text-muted-foreground">None</p>
                    ) : (
                        <div className="-mx-4 divide-y divide-border">
                            {billing.payments.map((payment) => (
                                <PaymentRow
                                    key={payment.id}
                                    payment={payment}
                                    aside={
                                        can('billing.refund') &&
                                        payment.status === 'succeeded' &&
                                        payment.purpose !== 'card_setup' ? (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setRefunding(payment)
                                                }
                                            >
                                                Refund
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
            {refunding && (
                <RefundDialog
                    payment={refunding}
                    onClose={() => setRefunding(null)}
                />
            )}
        </Card>
    );
}
