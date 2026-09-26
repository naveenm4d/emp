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
        public ?string $invitation_message = null,
        public ?string $reminder_message = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) $data['name']),
            email: GuestContact::email($data['email'] ?? null),
            phone: GuestContact::phone($data['phone'] ?? null),
            notes: $data['notes'] ?? null,
            invitation_message: $data['invitation_message'] ?? null,
            reminder_message: $data['reminder_message'] ?? null,
        );
    }
}
