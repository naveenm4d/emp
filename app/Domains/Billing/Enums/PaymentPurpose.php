<?php

namespace App\Domains\Billing\Enums;

use App\Core\Enums\Concerns\HasValues;

/** What a payment is for; decides what happens once it succeeds. */
enum PaymentPurpose: string
{
    use HasValues;

    /** Starts (or restarts) a Business subscription. */
    case Subscription = 'subscription';
    /** An automatic charge that renews a subscription for another period. */
    case Renewal = 'renewal';
    case EventCredits = 'event_credits';
    case ExtraGuests = 'extra_guests';
    case Template = 'template';
    /** An ended Enterprise plan renewed at the price staff set. */
    case EnterpriseRenewal = 'enterprise_renewal';
    /** Saves a card (PayHere preapproval); nothing is bought. */
    case CardSetup = 'card_setup';

    public function label(): string
    {
        return match ($this) {
            self::Subscription => 'Subscription',
            self::Renewal => 'Renewal',
            self::EventCredits => 'Event credits',
            self::ExtraGuests => 'Extra guests',
            self::Template => 'Invitation design',
            self::EnterpriseRenewal => 'Enterprise renewal',
            self::CardSetup => 'Card saved',
        };
    }

    /** These purposes keep the card for later charges, so a new card goes through preapproval. */
    public function savesCard(): bool
    {
        return $this === self::Subscription || $this === self::CardSetup;
    }
}
