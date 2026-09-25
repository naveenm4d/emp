<?php

namespace App\Domains\Event\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Event\Enums\EventState;

final readonly class EventFilters extends DataTransferObject
{
    public function __construct(
        public ?EventState $state = null,
        public ?string $search = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            state: EventState::tryFrom((string) ($data['state'] ?? '')),
            search: filled($data['search'] ?? null) ? (string) $data['search'] : null,
        );
    }

    public function toArray(): array
    {
        return ['state' => $this->state?->value, 'search' => $this->search];
    }
}
