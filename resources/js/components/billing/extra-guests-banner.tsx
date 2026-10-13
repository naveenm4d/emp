import { Users } from 'lucide-react';
import { useState } from 'react';

import {
    PurchaseDialog,
    useBillingWallet,
} from '@/components/billing/purchase-dialog';
import { Stepper } from '@/components/shared/form-section';
import { Button } from '@/components/ui/button';
import { useClientCan } from '@/lib/permissions';
import { useClientPlan } from '@/lib/plans';
import { extraGuests } from '@/routes/client/billing';
import type { Event } from '@/types';

/**
 * Near the event's guest limit (Celebration, Business): how many are left
 * and a way to buy more, in blocks. Hidden for people who can't pay.
 */
export function ExtraGuestsBanner({
    event,
    guests,
}: {
    event: Event;
    guests: number;
}) {
    const plan = useClientPlan();
    const can = useClientCan();
    const [open, setOpen] = useState(false);
    const [blocks, setBlocks] = useState(1);
    const wallet = useBillingWallet(open);
    const limit = event.guest_limit;

    if (
        limit === null ||
        limit === undefined ||
        !plan?.allows_extra_guests ||
        !can('billing.manage') ||
        guests < limit * 0.8
    ) {
        return null;
    }

    const block = wallet?.extra_guests_block ?? 100;
    const price = wallet?.prices.extra_guest_block;
    const left = Math.max(0, limit - guests);

    return (
        <>
            <div className="mb-3 flex flex-wrap items-center gap-3 rounded-2xl border border-warning/30 bg-warning-muted px-4 py-3 text-sm motion-safe:animate-fade-in">
                <Users className="size-4 shrink-0 text-warning" />
                <span className="min-w-0 flex-1">
                    {left === 0
                        ? `This event has reached its limit of ${limit} guests.`
                        : `${left} of ${limit} guest places left.`}
                </span>
                <Button size="sm" onClick={() => setOpen(true)}>
                    Buy more guests
                </Button>
            </div>
            <PurchaseDialog
                open={open}
                onClose={() => setOpen(false)}
                title="More guests"
                description={event.title}
                url={extraGuests.url(event)}
                data={{ blocks }}
                amount={price ? price.amount * blocks : null}
                currency={price?.currency}
            >
                <div className="flex items-center justify-between rounded-2xl bg-raised px-4 py-3">
                    <span className="text-sm">
                        <span className="font-semibold">
                            +{blocks * block} guests
                        </span>
                        <span className="block text-xs text-muted-foreground">
                            New limit: {limit + blocks * block}
                        </span>
                    </span>
                    <Stepper
                        label="Blocks of guests"
                        value={blocks}
                        min={1}
                        max={20}
                        onChange={setBlocks}
                    />
                </div>
            </PurchaseDialog>
        </>
    );
}
