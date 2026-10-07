<?php

namespace App\Domains\Client\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Client\Contracts\ClientUserRepositoryInterface;
use App\Domains\Client\Enums\ClientUserRole;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<ClientUser>
 */
class ClientUserRepository extends BaseRepository implements ClientUserRepositoryInterface
{
    protected function model(): string
    {
        return ClientUser::class;
    }

    public function findByEmail(string $email): ?ClientUser
    {
        return $this->query()->where('email', mb_strtolower(trim($email)))->first();
    }

    public function countForClient(string $clientId): int
    {
        return $this->query()->where('client_id', $clientId)->count();
    }

    public function forClient(string $clientId): Collection
    {
        return $this->query()
            ->where('client_id', $clientId)
            ->with('events:id')
            ->orderByRaw('case when role = ? then 0 else 1 end', [ClientUserRole::Owner->value])
            ->orderBy('name')
            ->get();
    }

    public function syncEvents(ClientUser $user, array $eventIds): void
    {
        $user->events()->sync($eventIds);
    }

    public function attachEvent(ClientUser $user, string $eventId): void
    {
        $user->events()->syncWithoutDetaching([$eventId]);
    }
}
