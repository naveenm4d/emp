import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';

import { StatusBadge } from '@/components/shared/status-badge';
import { formatDateTime, formatMoney } from '@/lib/format';
import type { Payment } from '@/types';

/** One payment in a billing history list: what, when, how much and where it stands. */
export function PaymentRow({
    payment,
    href,
    aside,
}: {
    payment: Payment;
    href?: string;
    aside?: ReactNode;
}) {
    const body = (
        <>
            <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-semibold">
                    {payment.description}
                </div>
                <div className="truncate text-xs text-muted-foreground">
                    {formatDateTime(payment.paid_at ?? payment.created_at)}
                    {payment.card && ` · ${payment.card}`}
                    {payment.automatic && ' · automatic renewal'}
                    {payment.client && ` · ${payment.client.name}`}
                </div>
            </div>
            <div className="flex shrink-0 flex-col items-end gap-1">
                <span className="text-sm font-bold tabular-nums">
                    {payment.purpose === 'card_setup'
                        ? '—'
                        : formatMoney(payment.amount, payment.currency)}
                </span>
                <StatusBadge
                    status={payment.status}
                    label={payment.status_label}
                />
            </div>
            {aside}
            {href && <ChevronRight className="size-4 shrink-0 text-subtle" />}
        </>
    );

    const className = 'flex items-center gap-3 px-4 py-3';

    return href ? (
        <Link href={href} className={`${className} hover:bg-raised`}>
            {body}
        </Link>
    ) : (
        <div className={className}>{body}</div>
    );
}
