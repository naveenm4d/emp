<?php

namespace App\Domains\Billing\Enums;

use App\Core\Enums\Concerns\HasValues;

/** How a payment is taken through PayHere. */
enum PaymentChannel: string
{
    use HasValues;

    /** A one-off payment on PayHere's checkout page; the card isn't kept. */
    case Checkout = 'checkout';
    /** The card is saved on PayHere's preapproval page, then charged from our server. */
    case Preapproval = 'preapproval';
    /** A saved card charged from our server (Charging API). */
    case SavedCard = 'saved_card';

    /** The payer leaves for a PayHere page to enter their card. */
    public function redirects(): bool
    {
        return $this !== self::SavedCard;
    }
}
