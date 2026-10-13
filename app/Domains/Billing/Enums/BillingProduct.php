<?php

namespace App\Domains\Billing\Enums;

use App\Core\Enums\Concerns\HasValues;

/** What clients can buy at a price staff set (paid templates carry their own price). */
enum BillingProduct: string
{
    use HasValues;

    case BusinessMonthly = 'business_monthly';
    case BusinessYearly = 'business_yearly';
    /** One Celebration event credit. */
    case CelebrationEvent = 'celebration_event';
    /** A block of extra guests for one event (config emp.extra_guests_block). */
    case ExtraGuestBlock = 'extra_guest_block';

    public function label(): string
    {
        return match ($this) {
            self::BusinessMonthly => 'Business, monthly',
            self::BusinessYearly => 'Business, yearly',
            self::CelebrationEvent => 'Celebration event',
            self::ExtraGuestBlock => 'Extra guests ('.(int) config('emp.extra_guests_block').')',
        };
    }
}
