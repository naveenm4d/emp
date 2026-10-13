import { Head, useForm } from '@inertiajs/react';
import { Tag } from 'lucide-react';
import type { FormEvent } from 'react';

import { FormField } from '@/components/shared/form-field';
import { Switch } from '@/components/shared/form-section';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { update } from '@/routes/admin/billing/prices';
import type { BillingPrice } from '@/types';

const HINTS: Record<BillingPrice['product'], string> = {
    business_monthly: 'Charged every month while auto-renew is on.',
    business_yearly: 'Charged every year while auto-renew is on.',
    celebration_event: 'One event credit (Celebration plan).',
    extra_guest_block: 'One block of extra guests for one event.',
};

/**
 * What plans and add-ons cost. A change applies to new purchases and the
 * next renewals; payments already made keep their amount. Paid designs are
 * priced on each template.
 */
export default function BillingPrices({
    prices,
    extraGuestsBlock,
}: {
    prices: { data: BillingPrice[] };
    extraGuestsBlock: number;
}) {
    return (
        <AdminLayout>
            <Head title="Prices" />
            <PageHeader
                title="Prices"
                description={`New purchases and the next renewals use these. Extra guests are sold in blocks of ${extraGuestsBlock}. Designs are priced on each template.`}
            />
            <div className="grid max-w-4xl gap-4 md:grid-cols-2">
                {prices.data.map((price) => (
                    <PriceCard key={price.product} price={price} />
                ))}
            </div>
        </AdminLayout>
    );
}

function PriceCard({ price }: { price: BillingPrice }) {
    const form = useForm({
        amount: String(price.amount / 100),
        is_active: price.is_active,
        note: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(update.url(price.product), {
            preserveScroll: true,
            onSuccess: () => form.reset('note'),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-3 rounded-2xl bg-card p-5 shadow-card"
            noValidate
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                    <span className="flex size-9 items-center justify-center rounded-xl bg-accent text-accent-foreground">
                        <Tag className="size-4" />
                    </span>
                    <div>
                        <h2 className="text-sm font-bold">{price.label}</h2>
                        <p className="text-xs text-muted-foreground">
                            {HINTS[price.product]}
                        </p>
                    </div>
                </div>
                <label className="flex items-center gap-2 text-xs font-semibold">
                    For sale
                    <Switch
                        checked={form.data.is_active}
                        onChange={(value) => form.setData('is_active', value)}
                    />
                </label>
            </div>
            <FormField
                label="Price (Rs.)"
                htmlFor={`amount-${price.product}`}
                error={form.errors.amount}
                hint={`Now ${formatMoney(price.amount, price.currency)} · updated ${formatDateTime(price.updated_at)}`}
            >
                <Input
                    id={`amount-${price.product}`}
                    type="number"
                    min={10}
                    step="0.01"
                    inputMode="decimal"
                    value={form.data.amount}
                    onChange={(e) => form.setData('amount', e.target.value)}
                />
            </FormField>
            <FormField
                label="Note"
                htmlFor={`note-${price.product}`}
                error={form.errors.note}
                hint="Required: why the price changes."
            >
                <Textarea
                    id={`note-${price.product}`}
                    rows={1}
                    maxLength={500}
                    value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)}
                />
            </FormField>
            <Button
                type="submit"
                className="self-start"
                disabled={form.processing || !form.isDirty}
            >
                Save
            </Button>
        </form>
    );
}
