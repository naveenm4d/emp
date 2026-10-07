<?php

namespace App\Domains\Client\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Client\Enums\ClientPermission;

/** What a member of a client account may do, and which events they see. */
final readonly class MemberAccessData extends DataTransferObject
{
    /**
     * @param  list<ClientPermission>  $permissions
     * @param  list<string>  $eventIds  ignored when $allEvents
     */
    public function __construct(
        public array $permissions,
        public bool $allEvents,
        public array $eventIds,
    ) {}

    /** @param array<string, mixed> $data validated input */
    public static function fromArray(array $data): self
    {
        $allEvents = (bool) ($data['all_events'] ?? false);

        return new self(
            permissions: array_values(array_unique(
                array_map(fn (string $permission) => ClientPermission::from($permission), $data['permissions'] ?? []),
                SORT_REGULAR,
            )),
            allEvents: $allEvents,
            eventIds: $allEvents ? [] : array_values(array_unique(array_map('strval', $data['event_ids'] ?? []))),
        );
    }

    /** @return list<string> */
    public function permissionValues(): array
    {
        return array_map(fn (ClientPermission $permission) => $permission->value, $this->permissions);
    }
}
