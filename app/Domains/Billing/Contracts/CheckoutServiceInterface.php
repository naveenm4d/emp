<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\DTOs\BillingDetailsData;
use App\Domains\Billing\DTOs\GatewayRedirect;
use App\Domains\Billing\Enums\BillingInterval;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Event\Models\Event;
use App\Domains\Template\Models\Template;

/**
 * Starting a purchase. Prices come from the server, never the request. With a
 * saved card the charge happens right away and the payment comes back;
 * otherwise a checkout comes back and the payer goes to PayHere (see
 * redirect()). Nothing is recorded as a payment until PayHere processed it.
 */
interface CheckoutServiceInterface
{
    /** Business, monthly or yearly; renews on its own. A new card is saved for the renewals. */
    public function subscribe(ClientUser $by, BillingInterval $interval, ?PaymentMethod $card): Payment|Checkout;

    /** Celebration event credits (Starter moves to Celebration). */
    public function buyEventCredits(ClientUser $by, int $quantity, ?PaymentMethod $card): Payment|Checkout;

    /** Blocks of extra guests for one event. */
    public function buyExtraGuests(ClientUser $by, Event $event, int $blocks, ?PaymentMethod $card): Payment|Checkout;

    /** A paid invitation design, for every event of the account. */
    public function buyTemplate(ClientUser $by, Template $template, ?PaymentMethod $card): Payment|Checkout;

    /** Renews an ended Enterprise plan at the price and length staff set. */
    public function renewEnterprise(ClientUser $by, ?PaymentMethod $card): Payment|Checkout;

    /** Saves a new card on PayHere's preapproval page; nothing is bought. */
    public function addCard(ClientUser $by): Checkout;

    /** The signed form that takes the payer to PayHere for a checkout. */
    public function redirect(Checkout $checkout): GatewayRedirect;

    public function saveBillingDetails(Client $client, BillingDetailsData $details): void;
}
