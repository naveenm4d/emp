<?php

namespace App\Domains\Client\DTOs;

use App\Core\DTOs\DataTransferObject;

/** A client account created by staff; without a password the owner is invited to choose one. */
final readonly class CreateClientData extends DataTransferObject
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) $data['name']),
            email: mb_strtolower(trim((string) $data['email'])),
            password: filled($data['password'] ?? null) ? (string) $data['password'] : null,
        );
    }
}
