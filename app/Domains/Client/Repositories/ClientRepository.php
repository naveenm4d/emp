<?php

namespace App\Domains\Client\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Client\Contracts\ClientRepositoryInterface;
use App\Domains\Client\DTOs\ClientFilters;
use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<Client>
 */
class ClientRepository extends BaseRepository implements ClientRepositoryInterface
{
    protected function model(): string
    {
        return Client::class;
    }

    public function findAndLock(string $id): Client
    {
        /** @var Client */
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    public function eventsCreatedCount(string $clientId): int
    {
        return Event::withTrashed()->where('client_id', $clientId)->count();
    }

    public function findByEmail(string $email): ?Client
    {
        return $this->query()->where('email', mb_strtolower($email))->first();
    }

    public function search(ClientFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->withCount('events')
            ->when($filters->search, fn ($query, string $search) => $query->where(
                fn ($query) => $query
                    ->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('email', "%{$search}%", caseSensitive: false),
            ))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
