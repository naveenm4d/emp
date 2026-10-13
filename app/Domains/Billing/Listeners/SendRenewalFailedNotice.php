<?php

namespace App\Domains\Billing\Listeners;

use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Events\PaymentFailed;
use App\Domains\Billing\Notifications\RenewalFailed;

/** Tells the account owner a renewal was declined and when the plan ends. */
class SendRenewalFailedNotice
{
    public function handle(PaymentFailed $event): void
    {
        $payment = $event->payment;

        if ($payment->purpose !== PaymentPurpose::Renewal || $payment->subscription === null) {
            return;
        }

        $payment->client->owner?->notify(new RenewalFailed($payment->subscription, $payment->status_message));
    }
}
