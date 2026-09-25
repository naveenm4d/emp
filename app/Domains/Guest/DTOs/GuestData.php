<?php

namespace App\Domains\Guest\DTOs;

use App\Core\DTOs\DataTransferObject;

final readonly class GuestData extends DataTransferObject
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $notes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) $data['name']),
            email: GuestContact::email($data['email'] ?? null),
            phone: GuestContact::phone($data['phone'] ?? null),
            notes: $data['notes'] ?? null,
        );
    }
}
