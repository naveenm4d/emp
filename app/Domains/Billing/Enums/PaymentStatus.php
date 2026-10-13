<?php

namespace App\Domains\Billing\Enums;

use App\Core\Enums\Concerns\HasValues;

/**
 * PayHere's verdict on an order. A payment is recorded as Succeeded or Failed
 * (PayHere processed it); Pending and Canceled only describe a checkout that
 * is still waiting or that the payer left, and are never stored. A failed
 * payment can still succeed (the payer tried another card on PayHere's
 * page); only a successful one can be refunded or charged back.
 */
enum PaymentStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
    case Chargedback = 'chargedback';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Succeeded => 'Paid',
            self::Failed => 'Declined',
            self::Canceled => 'Canceled',
            self::Refunded => 'Refunded',
            self::Chargedback => 'Charged back',
        };
    }

    /** PayHere's status_code: 2 success, 0 pending, -1 canceled, -2 failed, -3 charged back. */
    public static function fromPayHere(int $code): self
    {
        return match ($code) {
            2 => self::Succeeded,
            0 => self::Pending,
            -1 => self::Canceled,
            -3 => self::Chargedback,
            default => self::Failed,
        };
    }

    /** PayHere processed the order: it becomes a payment. */
    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    public function canBecome(self $next): bool
    {
        return match ($this) {
            self::Failed => $next === self::Succeeded,
            self::Succeeded => $next === self::Refunded || $next === self::Chargedback,
            default => false,
        };
    }

    /** @return list<array{value: string, label: string}> the statuses a payment can have, for filters */
    public static function recordedOptions(): array
    {
        return array_map(
            fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
            [self::Succeeded, self::Failed, self::Refunded, self::Chargedback],
        );
    }
}
