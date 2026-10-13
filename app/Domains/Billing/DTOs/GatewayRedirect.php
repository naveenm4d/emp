<?php

namespace App\Domains\Billing\DTOs;

use App\Core\DTOs\DataTransferObject;

/** Where the payer's browser goes to enter their card: a form posted to PayHere (or the mock page). */
final readonly class GatewayRedirect extends DataTransferObject
{
    /** @param array<string, string> $fields */
    public function __construct(
        public string $url,
        public string $method,
        public array $fields,
    ) {}
}
