<?php

namespace App\Domains\Event\Services;

use App\Domains\Client\Models\Client;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Contracts\EventRepositoryInterface;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EventQueryService implements EventQueryServiceInterface
{
    public function __construct(
        private readonly EventRepositoryInterface $events,
    ) {}

    public function forClient(Client $client, EventFilters $filters): LengthAwarePaginator
    {
        return $this->events->paginateForClient($client->id, $filters, config('emp.per_page'));
    }

    public function all(EventFilters $filters): LengthAwarePaginator
    {
        return $this->events->paginateAll($filters, config('emp.per_page'));
    }

    public function findLatestBySlug(string $slug): ?Event
    {
        return $this->events->findBySlug($slug);
    }

    public function findAndLock(string $id): Event
    {
        return $this->events->findAndLock($id);
    }

    public function count(): int
    {
        return $this->events->count();
    }

    public function countByState(?Client $client = null): array
    {
        return $this->events->countByState($client?->id);
    }
}
