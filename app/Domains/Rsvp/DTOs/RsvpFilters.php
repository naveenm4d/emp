<?php

namespace App\Domains\Rsvp\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Rsvp\Enums\RsvpStatus;

final readonly class RsvpFilters extends DataTransferObject
{
    public function __construct(
        public ?RsvpStatus $status = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(status: RsvpStatus::tryFrom((string) ($data['status'] ?? '')));
    }

    public function toArray(): array
    {
        return ['status' => $this->status?->value];
    }
}
