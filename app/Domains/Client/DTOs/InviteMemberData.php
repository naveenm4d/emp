<?php

namespace App\Domains\Client\DTOs;

use App\Core\DTOs\DataTransferObject;

/** Someone invited to sign in to a client account. */
final readonly class InviteMemberData extends DataTransferObject
{
    public function __construct(
        public string $name,
        public string $email,
        public MemberAccessData $access,
    ) {}

    /** @param array<string, mixed> $data validated input */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) $data['name']),
            email: mb_strtolower(trim((string) $data['email'])),
            access: MemberAccessData::fromArray($data),
        );
    }
}
