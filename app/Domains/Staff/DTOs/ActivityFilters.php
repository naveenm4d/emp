<?php

namespace App\Domains\Staff\DTOs;

use App\Core\DTOs\DataTransferObject;

final readonly class ActivityFilters extends DataTransferObject
{
    public function __construct(
        public ?string $staffMemberId = null,
        public ?string $clientId = null,
        public ?string $action = null,
    ) {}

    /** @param array<string, mixed> $query */
    public static function fromArray(array $query): self
    {
        $value = fn (string $key) => is_string($query[$key] ?? null) && $query[$key] !== '' ? $query[$key] : null;

        return new self($value('staff'), $value('client'), $value('action'));
    }

    /** @return array{staff: string|null, client: string|null, action: string|null} */
    public function toQuery(): array
    {
        return ['staff' => $this->staffMemberId, 'client' => $this->clientId, 'action' => $this->action];
    }
}
