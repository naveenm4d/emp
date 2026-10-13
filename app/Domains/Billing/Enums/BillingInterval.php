<?php

namespace App\Domains\Billing\Enums;

use App\Core\Enums\Concerns\HasValues;
use Carbon\CarbonImmutable;

/** How often a Business subscription renews. */
enum BillingInterval: string
{
    use HasValues;

    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }

    public function product(): BillingProduct
    {
        return match ($this) {
            self::Monthly => BillingProduct::BusinessMonthly,
            self::Yearly => BillingProduct::BusinessYearly,
        };
    }

    /** The end of a period that starts at $start (31 Jan + 1 month = 28/29 Feb). */
    public function periodEnd(CarbonImmutable $start): CarbonImmutable
    {
        return match ($this) {
            self::Monthly => $start->addMonthNoOverflow(),
            self::Yearly => $start->addYearNoOverflow(),
        };
    }
}
