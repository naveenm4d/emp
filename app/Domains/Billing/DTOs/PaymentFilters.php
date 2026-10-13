<?php

namespace App\Domains\Billing\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\PaymentStatus;

/** Staff filtering the payments list. */
final readonly class PaymentFilters extends DataTransferObject
{
    public function __construct(
        public ?string $search = null,
        public ?PaymentStatus $status = null,
        public ?PaymentPurpose $purpose = null,
        public ?string $clientId = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            search: filled($data['search'] ?? null) ? (string) $data['search'] : null,
            status: PaymentStatus::tryFrom((string) ($data['status'] ?? '')),
            purpose: PaymentPurpose::tryFrom((string) ($data['purpose'] ?? '')),
            clientId: filled($data['client_id'] ?? null) ? (string) $data['client_id'] : null,
        );
    }
}
