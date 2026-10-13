<?php

namespace App\Domains\Billing\Events;

use App\Domains\Billing\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A payment went through and what it bought was applied (receipt email). */
final class PaymentSucceeded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Payment $payment,
    ) {}
}
