import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { extraGuests } from '@/routes/admin/clients/events';
import type { Client, Event } from '@/types';

/** Guests sold per block, and the block's price (config emp.extra_guests_*). */
const BLOCK = 100;
const BLOCK_PRICE = 1000;

const rupees = (amount: number) =>
    `Rs. ${new Intl.NumberFormat('en-LK').format(amount)}`;

/**
 * Staff only: extra guests on top of the plan's limit (Celebration, Business),
 * sold in blocks of 100 for Rs. 1,000 each. A note is required; it's logged.
 */
export function ExtraGuestsCard({
    event,
    client,
}: {
    event: Event;
    client: Client;
}) {
    const form = useForm({ blocks: event.extra_guests / BLOCK, note: '' });
    const base = (event.guest_limit ?? 0) - event.extra_guests;
    const blocks = Number(form.data.blocks) || 0;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(extraGuests.url({ client: client.id, event: event.id }), {
            preserveScroll: true,
            onSuccess: () => form.reset('note'),
        });
    };

    return (
        <Card className="mb-6">
            <CardHeader>
                <CardTitle>Extra guests</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="mb-3 text-sm text-muted-foreground">
                    {client.plan_label}: {base} guests, plus {BLOCK} per extra
                    block ({rupees(BLOCK_PRICE)} each). Now allowed:{' '}
                    <span className="font-medium text-foreground">
                        {event.guest_limit} guests
                    </span>
                    .
                </p>
                <form
                    onSubmit={submit}
                    className="grid gap-4 sm:grid-cols-[10rem_1fr_auto] sm:items-end"
                >
                    <FormField
                        label="Extra blocks"
                        htmlFor="extra_blocks"
                        error={form.errors.blocks}
                        hint={`= ${base + blocks * BLOCK} guests · ${rupees(blocks * BLOCK_PRICE)}`}
                    >
                        <Input
                            id="extra_blocks"
                            type="number"
                            min={0}
                            max={100}
                            value={form.data.blocks}
                            onChange={(e) =>
                                form.setData('blocks', Number(e.target.value))
                            }
                        />
                    </FormField>
                    <FormField
                        label="Note"
                        htmlFor="extra_note"
                        error={form.errors.note}
                        hint="Required: e.g. the payment reference."
                    >
                        <Textarea
                            id="extra_note"
                            rows={1}
                            maxLength={500}
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                        />
                    </FormField>
                    <Button type="submit" disabled={form.processing}>
                        Save
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
