<?php

namespace App\Domains\Event\Contracts;

use App\Domains\Client\Models\Client;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EventQueryServiceInterface
{
    /** @return LengthAwarePaginator<int, Event> */
    public function forClient(Client $client, EventFilters $filters): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, Event> */
    public function all(EventFilters $filters): LengthAwarePaginator;

    /** Public lookup: only published events are visible. */
    public function findPublishedBySlug(string $slug): Event;

    /** Loads the event with a row lock. Must be called inside a transaction. */
    public function findAndLock(string $id): Event;

    public function count(): int;

    /** @return array<string, int> */
    public function countByState(?Client $client = null): array;
}
