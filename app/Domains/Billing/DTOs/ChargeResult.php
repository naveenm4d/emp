<?php

namespace App\Domains\Billing\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Billing\Enums\PaymentStatus;

/** What PayHere answered when a saved card was charged. */
final readonly class ChargeResult extends DataTransferObject
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $paymentId = null,
        public ?string $message = null,
    ) {}
}
