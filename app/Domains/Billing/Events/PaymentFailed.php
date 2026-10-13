<?php

namespace App\Domains\Billing\Events;

use App\Domains\Billing\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A payment was declined or canceled (a failed renewal emails the owner). */
final class PaymentFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Payment $payment,
    ) {}
}
