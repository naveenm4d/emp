<?php

namespace App\Domains\Billing\Enums;

use App\Core\Enums\Concerns\HasValues;

enum SubscriptionStatus: string
{
    use HasValues;

    /** Renews automatically at the end of the period. */
    case Active = 'active';
    /** Auto-renew is off: runs until the end of the period, then ends. */
    case Canceled = 'canceled';
    /** Ended (period over, renewal failed or staff changed the plan). */
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Canceled => 'Canceled',
            self::Expired => 'Ended',
        };
    }
}
