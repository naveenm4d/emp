<?php

namespace App\Domains\Event\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Event>
 */
interface EventRepositoryInterface extends RepositoryInterface
{
    /** The newest event with this slug; slugs are not unique. */
    public function findBySlug(string $slug): ?Event;

    /** Loads the event with a row lock. Must be called inside a transaction. */
    public function findAndLock(string $id): Event;

    /**
     * The client's events; with $memberId, only those that member of the account was given.
     *
     * @return LengthAwarePaginator<int, Event>
     */
    public function paginateForClient(string $clientId, EventFilters $filters, int $perPage, ?string $memberId = null): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, Event> */
    public function paginateAll(EventFilters $filters, int $perPage): LengthAwarePaginator;

    /**
     * Figures for a client's events list header.
     *
     * @return array{upcoming: int, drafts: int, waiting: int}
     */
    public function totalsForClient(string $clientId, ?string $memberId = null): array;

    /**
     * Every event of the client (id, title, date), newest date first, for pickers.
     *
     * @return Collection<int, Event>
     */
    public function listForClient(string $clientId): Collection;

    /** @return array<string, int> state => count */
    public function countByState(?string $clientId = null): array;

    /** Store where the generated invitation lives, without touching updated_at. */
    public function recordRender(Event $event, string $path, string $hash): void;
}
