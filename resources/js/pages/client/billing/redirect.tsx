import { Head } from '@inertiajs/react';
import { Loader2, Lock } from 'lucide-react';
import { useEffect, useRef } from 'react';

import { formatMoney } from '@/lib/format';
import type { Checkout } from '@/types';

/**
 * Hands the payer over to PayHere: a form signed by the server (the hash
 * never needs the secret here) posted on load. The browser keeps its
 * Referer, which PayHere requires. The button is there if scripts are slow.
 */
export default function BillingRedirect({
    checkout,
    gateway,
}: {
    checkout: { data: Checkout };
    gateway: { url: string; fields: Record<string, string> };
}) {
    const form = useRef<HTMLFormElement>(null);
    const item = checkout.data;

    useEffect(() => {
        form.current?.submit();
    }, []);

    return (
        <main className="flex min-h-dvh items-center justify-center bg-background p-6 text-foreground">
            <Head title="Redirecting to PayHere" />
            <form
                ref={form}
                method="post"
                action={gateway.url}
                className="flex w-full max-w-sm flex-col items-center gap-4 rounded-3xl bg-card p-8 text-center shadow-card"
            >
                {Object.entries(gateway.fields).map(([name, value]) => (
                    <input key={name} type="hidden" name={name} value={value} />
                ))}
                <Loader2 className="size-8 animate-spin text-primary" />
                <div>
                    <h1 className="text-lg font-bold">
                        Taking you to PayHere…
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {item.description} ·{' '}
                        {formatMoney(item.amount, item.currency)}
                    </p>
                </div>
                <button
                    type="submit"
                    className="text-sm font-semibold text-link"
                >
                    Continue
                </button>
                <p className="flex items-center gap-1 text-[11px] text-muted-foreground">
                    <Lock className="size-3" /> You’ll enter your card on
                    PayHere’s secure page.
                </p>
            </form>
        </main>
    );
}
