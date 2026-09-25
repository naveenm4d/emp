<?php

namespace App\Domains\Invitation\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Invitation\Enums\InvitationStatus;

final readonly class InvitationFilters extends DataTransferObject
{
    public function __construct(
        public ?InvitationStatus $status = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(status: InvitationStatus::tryFrom((string) ($data['status'] ?? '')));
    }

    public function toArray(): array
    {
        return ['status' => $this->status?->value];
    }
}
