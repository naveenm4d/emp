<?php

namespace App\Domains\Notification\DTOs;

use App\Core\DTOs\DataTransferObject;
use Carbon\CarbonImmutable;

/**
 * A delivery status callback from a provider (e.g. WhatsApp webhook).
 */
final readonly class ProviderStatusUpdate extends DataTransferObject
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $messageId,
        public string $status,
        public ?CarbonImmutable $occurredAt = null,
        public ?string $error = null,
        public array $payload = [],
    ) {}
}
