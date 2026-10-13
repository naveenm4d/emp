<?php

namespace App\Domains\Billing\Listeners;

use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Events\PaymentSucceeded;
use App\Domains\Billing\Notifications\PaymentReceipt;

/** Emails the account owner a receipt for everything bought (not for saving a card). */
class SendPaymentReceipt
{
    public function handle(PaymentSucceeded $event): void
    {
        if ($event->payment->purpose === PaymentPurpose::CardSetup) {
            return;
        }

        $event->payment->client->owner?->notify(new PaymentReceipt($event->payment));
    }
}
