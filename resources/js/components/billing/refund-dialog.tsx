import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { POPUP } from '@/components/web/invitation/registration-dialog';
import { formatMoney } from '@/lib/format';
import { refund } from '@/routes/admin/billing/payments';
import type { Payment } from '@/types';

/** Staff: refund a payment in full through PayHere. The note is required and logged. */
export function RefundDialog({
    payment,
    onClose,
}: {
    payment: Payment;
    onClose: () => void;
}) {
    const form = useForm({ note: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(refund.url(payment), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className={POPUP}>
                <form
                    onSubmit={submit}
                    className="flex flex-col gap-4 p-4"
                    noValidate
                >
                    <div className="pr-8">
                        <DialogDescription className="text-xs">
                            {payment.order_id} · {payment.description}
                        </DialogDescription>
                        <DialogTitle className="text-base font-bold">
                            Refund{' '}
                            {formatMoney(payment.amount, payment.currency)}?
                        </DialogTitle>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        PayHere returns the full amount to the card. What was
                        bought stays: change the plan or extra guests separately
                        if needed.
                    </p>
                    <FormField
                        label="Why"
                        htmlFor="refund-note"
                        error={form.errors.note}
                    >
                        <Textarea
                            id="refund-note"
                            rows={2}
                            maxLength={255}
                            autoFocus
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                        />
                    </FormField>
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={form.processing}
                    >
                        Refund
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
