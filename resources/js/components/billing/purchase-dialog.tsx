import { router, usePage } from '@inertiajs/react';
import { CreditCard, Loader2, Lock, Plus } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { POPUP } from '@/components/web/invitation/registration-dialog';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { details as detailsRoute } from '@/routes/client/billing';
import type { BillingDetails, BillingWallet } from '@/types';

/**
 * The account's saved cards, billing details and prices (shared
 * `billingWallet`), loaded the first time `load` is true. undefined while
 * loading; null when the user can't pay (no billing permission).
 */
export function useBillingWallet(
    load: boolean,
): BillingWallet | null | undefined {
    const wallet = usePage().props.billingWallet;

    useEffect(() => {
        if (load && wallet === undefined) {
            router.reload({ only: ['billingWallet'] });
        }
    }, [load, wallet]);

    return wallet;
}

const NEW_CARD = 'new';

/** True when PayHere has the phone, address and city it asks for. */
function hasDetails(details: BillingDetails | null): boolean {
    return !!(details?.phone && details.address && details.city);
}

/**
 * Paying for something: the total, then a saved card (charged right away) or
 * a new card on PayHere's secure page. The price shown is only a preview;
 * the server charges its own price. Billing details are asked for once,
 * when a new card needs them.
 */
export function PurchaseDialog({
    open,
    onClose,
    title,
    description,
    url,
    data = {},
    amount,
    currency = 'LKR',
    savesCard = false,
    submitLabel,
    children,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description?: string;
    /** Where the purchase is posted. */
    url: string;
    /** What is bought (interval, quantity, blocks); never an amount. */
    data?: Record<string, string | number>;
    /** The total in minor units, from the wallet's prices; null while loading. */
    amount: number | null;
    currency?: string;
    /** A new card is kept for renewals (subscriptions). */
    savesCard?: boolean;
    submitLabel?: string;
    children?: ReactNode;
}) {
    const wallet = useBillingWallet(open);
    const cards = (wallet?.cards ?? []).filter(
        (card) => !card.is_expired && !card.removed,
    );
    const [choice, setChoice] = useState<string | null>(null);
    const selected =
        choice ??
        cards.find((card) => card.is_default)?.id ??
        cards[0]?.id ??
        NEW_CARD;
    const needsDetails =
        selected === NEW_CARD && wallet && !hasDetails(wallet.details);

    const [details, setDetails] = useState<BillingDetails>({});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (wallet?.details) {
            setDetails(wallet.details);
        }
    }, [wallet?.details]);

    const purchase = () =>
        router.post(
            url,
            {
                ...data,
                payment_method_id: selected === NEW_CARD ? null : selected,
            },
            {
                preserveScroll: true,
                onError: (e) => setErrors(e),
                onFinish: () => setProcessing(false),
                onSuccess: () => onClose(),
            },
        );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        if (!needsDetails) {
            purchase();

            return;
        }

        router.put(detailsRoute.url(), details, {
            preserveScroll: true,
            only: ['billingWallet', 'flash', 'errors'],
            onSuccess: () => purchase(),
            onError: (e) => {
                setErrors(e);
                setProcessing(false);
            },
        });
    };

    const label =
        submitLabel ??
        (selected === NEW_CARD
            ? 'Continue to PayHere'
            : amount !== null
              ? `Pay ${formatMoney(amount, currency)}`
              : 'Pay');

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className={POPUP}>
                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                    noValidate
                >
                    <div className="px-4 pt-5 pr-12">
                        {description && (
                            <DialogDescription className="text-xs">
                                {description}
                            </DialogDescription>
                        )}
                        <DialogTitle className="text-base font-bold">
                            {title}
                        </DialogTitle>
                    </div>

                    <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-4 py-4">
                        {children}

                        <div className="flex items-baseline justify-between rounded-2xl bg-raised px-4 py-3">
                            <span className="text-sm font-semibold">Total</span>
                            <span className="text-xl font-bold tabular-nums">
                                {amount === null ? (
                                    <span className="inline-block h-6 w-24 animate-pulse rounded bg-foreground/10" />
                                ) : (
                                    formatMoney(amount, currency)
                                )}
                            </span>
                        </div>

                        {wallet === undefined ? (
                            <div className="h-24 animate-pulse rounded-2xl bg-foreground/6" />
                        ) : wallet === null ? (
                            <p className="text-sm text-muted-foreground">
                                Ask the account owner for the “Manage billing”
                                permission to pay.
                            </p>
                        ) : (
                            <fieldset className="flex flex-col gap-2">
                                <legend className="mb-2 text-xs font-semibold text-muted-foreground">
                                    Pay with
                                </legend>
                                {cards.map((card) => (
                                    <CardOption
                                        key={card.id}
                                        checked={selected === card.id}
                                        onSelect={() => setChoice(card.id)}
                                        title={card.label}
                                        hint={
                                            card.expiry
                                                ? `Expires ${card.expiry}${card.is_default ? ' · default' : ''}`
                                                : undefined
                                        }
                                        icon={<CreditCard className="size-4" />}
                                    />
                                ))}
                                <CardOption
                                    checked={selected === NEW_CARD}
                                    onSelect={() => setChoice(NEW_CARD)}
                                    title="A new card"
                                    hint={
                                        savesCard
                                            ? 'Entered on PayHere and saved for renewals.'
                                            : 'Entered on PayHere’s secure page.'
                                    }
                                    icon={<Plus className="size-4" />}
                                />
                                {errors.payment_method_id && (
                                    <p className="text-xs text-destructive">
                                        {errors.payment_method_id}
                                    </p>
                                )}
                            </fieldset>
                        )}

                        {needsDetails && (
                            <div className="grid gap-3 motion-safe:animate-fade-in">
                                <p className="text-xs text-muted-foreground">
                                    PayHere asks for a billing contact. We keep
                                    it for next time.
                                </p>
                                <FormField
                                    label="Phone"
                                    htmlFor="billing-phone"
                                    error={errors.phone}
                                >
                                    <Input
                                        id="billing-phone"
                                        type="tel"
                                        autoComplete="tel"
                                        value={details.phone ?? ''}
                                        onChange={(e) =>
                                            setDetails({
                                                ...details,
                                                phone: e.target.value,
                                            })
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="Address"
                                    htmlFor="billing-address"
                                    error={errors.address}
                                >
                                    <Input
                                        id="billing-address"
                                        autoComplete="street-address"
                                        value={details.address ?? ''}
                                        onChange={(e) =>
                                            setDetails({
                                                ...details,
                                                address: e.target.value,
                                            })
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="City"
                                    htmlFor="billing-city"
                                    error={errors.city}
                                >
                                    <Input
                                        id="billing-city"
                                        autoComplete="address-level2"
                                        value={details.city ?? ''}
                                        onChange={(e) =>
                                            setDetails({
                                                ...details,
                                                city: e.target.value,
                                            })
                                        }
                                    />
                                </FormField>
                            </div>
                        )}
                    </div>

                    <div className="flex flex-col gap-2 border-t border-border px-4 py-3">
                        <Button
                            type="submit"
                            size="lg"
                            disabled={processing || !wallet || amount === null}
                        >
                            {processing && (
                                <Loader2 className="size-4 animate-spin" />
                            )}
                            {label}
                        </Button>
                        <p className="flex items-center justify-center gap-1 text-[11px] text-muted-foreground">
                            <Lock className="size-3" /> Secured by PayHere. Card
                            numbers never reach us.
                        </p>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CardOption({
    checked,
    onSelect,
    title,
    hint,
    icon,
}: {
    checked: boolean;
    onSelect: () => void;
    title: string;
    hint?: string;
    icon: ReactNode;
}) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 transition-colors',
                checked
                    ? 'border-primary bg-primary/5'
                    : 'border-border hover:bg-raised',
            )}
        >
            <input
                type="radio"
                name="payment_method"
                className="sr-only"
                checked={checked}
                onChange={onSelect}
            />
            <span
                className={cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                    checked
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-raised',
                )}
            >
                {icon}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold">{title}</span>
                {hint && (
                    <span className="block text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </span>
            <span
                className={cn(
                    'size-4 shrink-0 rounded-full border-2',
                    checked ? 'border-primary bg-primary' : 'border-input',
                )}
            />
        </label>
    );
}
