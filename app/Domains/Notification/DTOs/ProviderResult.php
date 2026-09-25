<?php

namespace App\Domains\Notification\DTOs;

use App\Core\DTOs\DataTransferObject;

/**
 * What a channel provider returns after accepting a message.
 */
final readonly class ProviderResult extends DataTransferObject
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $messageId,
        public array $payload = [],
    ) {}
}
