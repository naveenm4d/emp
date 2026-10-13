import { Head, router } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { complete } from '@/routes/client/billing/mock';
import type { Checkout } from '@/types';

/** Local stand-in for PayHere's page (no credentials): pick how the payment ends. */
export default function BillingMock({
    checkout: wrapped,
}: {
    checkout: { data: Checkout };
}) {
    const payment = wrapped.data;
    const finish = (outcome: string) =>
        router.post(complete.url(payment), { outcome });
    const savesCard = payment.channel === 'preapproval';

    return (
        <main className="flex min-h-dvh items-center justify-center bg-background p-6 text-foreground">
            <Head title="Mock PayHere" />
            <div className="flex w-full max-w-sm flex-col gap-5 rounded-3xl bg-card p-6 shadow-card">
                <div className="flex items-center gap-2 rounded-xl bg-warning-muted px-3 py-2 text-xs font-semibold text-warning">
                    <FlaskConical className="size-4" /> Mock payment gateway: no
                    money moves.
                </div>
                <div>
                    <div className="text-xs text-muted-foreground">
                        {payment.order_id}
                    </div>
                    <h1 className="text-lg font-bold">{payment.description}</h1>
                    <div className="mt-1 text-2xl font-bold tabular-nums">
                        {formatMoney(payment.amount, payment.currency)}
                    </div>
                </div>
                <div className="flex flex-col gap-2">
                    <Button size="lg" onClick={() => finish('succeed')}>
                        Pay{savesCard && ' and save card ••4242'}
                    </Button>
                    {savesCard && (
                        <Button
                            variant="outline"
                            onClick={() => finish('decline_later')}
                        >
                            Save a card that declines renewals
                        </Button>
                    )}
                    <Button
                        variant="destructive"
                        onClick={() => finish('fail')}
                    >
                        Decline
                    </Button>
                    <Button variant="ghost" onClick={() => finish('cancel')}>
                        Cancel
                    </Button>
                </div>
            </div>
        </main>
    );
}
