<?php

namespace App\Domains\Client\DTOs;

use App\Core\DTOs\DataTransferObject;

final readonly class ClientFilters extends DataTransferObject
{
    public function __construct(
        public ?string $search = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(search: filled($data['search'] ?? null) ? (string) $data['search'] : null);
    }
}
