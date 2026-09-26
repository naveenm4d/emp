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
        public ?int $invited_additional_guests = null,
        public ?int $invited_children = null,
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
            invited_additional_guests: isset($data['invited_additional_guests']) ? (int) $data['invited_additional_guests'] : null,
            invited_children: isset($data['invited_children']) ? (int) $data['invited_children'] : null,
        );
    }
}
