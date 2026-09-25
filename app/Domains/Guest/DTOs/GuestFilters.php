<?php

namespace App\Domains\Guest\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\GuestSource;
use App\Domains\Guest\Enums\RsvpStatus;

final readonly class GuestFilters extends DataTransferObject
{
    public function __construct(
        public ?GuestSource $source = null,
        public ?ApprovalStatus $approvalStatus = null,
        public ?RsvpStatus $rsvpStatus = null,
        public ?string $search = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            source: GuestSource::tryFrom((string) ($data['source'] ?? '')),
            approvalStatus: ApprovalStatus::tryFrom((string) ($data['approval_status'] ?? '')),
            rsvpStatus: RsvpStatus::tryFrom((string) ($data['rsvp_status'] ?? '')),
            search: filled($data['search'] ?? null) ? (string) $data['search'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'source' => $this->source?->value,
            'approval_status' => $this->approvalStatus?->value,
            'rsvp_status' => $this->rsvpStatus?->value,
            'search' => $this->search,
        ];
    }
}
