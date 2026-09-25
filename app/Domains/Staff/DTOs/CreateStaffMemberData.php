<?php

namespace App\Domains\Staff\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Enums\StaffRole;

final readonly class CreateStaffMemberData extends DataTransferObject
{
    /**
     * @param  list<StaffPermission>|null  $permissions  null = role defaults
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public StaffRole $role = StaffRole::Viewer,
        public ?array $permissions = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['name']),
            email: mb_strtolower(trim($data['email'])),
            password: $data['password'],
            role: StaffRole::from($data['role'] ?? StaffRole::Viewer->value),
            permissions: isset($data['permissions'])
                ? array_values(array_map(StaffPermission::from(...), $data['permissions']))
                : null,
        );
    }
}
