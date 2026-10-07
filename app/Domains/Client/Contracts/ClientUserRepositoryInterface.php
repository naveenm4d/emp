<?php

namespace App\Domains\Client\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<ClientUser>
 */
interface ClientUserRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?ClientUser;

    /** Users of the account, pending invitations included. */
    public function countForClient(string $clientId): int;

    /**
     * The account's users, owner first, with the ids of the events members were given.
     *
     * @return Collection<int, ClientUser>
     */
    public function forClient(string $clientId): Collection;

    /** @param list<string> $eventIds */
    public function syncEvents(ClientUser $user, array $eventIds): void;

    public function attachEvent(ClientUser $user, string $eventId): void;
}
