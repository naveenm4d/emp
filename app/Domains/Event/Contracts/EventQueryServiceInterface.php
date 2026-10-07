<?php

namespace App\Domains\Event\Contracts;

use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EventQueryServiceInterface
{
    /**
     * The client's events with response figures and message issues; with a
     * viewer, only the events that user of the account can see.
     *
     * @return LengthAwarePaginator<int, Event>
     */
    public function forClient(Client $client, EventFilters $filters, ?ClientUser $viewer = null): LengthAwarePaginator;

    /**
     * Upcoming events the user can see (soonest first) for the event switcher.
     *
     * @return LengthAwarePaginator<int, Event>
     */
    public function forSwitcher(ClientUser $viewer): LengthAwarePaginator;

    /**
     * Upcoming and draft event counts, and parties waiting on a reply across upcoming events.
     *
     * @return array{upcoming: int, drafts: int, waiting: int}
     */
    public function totalsForClient(Client $client, ?ClientUser $viewer = null): array;

    /**
     * Every event with response figures and message issues (staff).
     *
     * @return LengthAwarePaginator<int, Event>
     */
    public function all(EventFilters $filters): LengthAwarePaginator;

    /** The newest event with this slug (slugs are not unique); only for redirecting old /e/{slug} links. */
    public function findLatestBySlug(string $slug): ?Event;

    /** Loads the event with a row lock. Must be called inside a transaction. */
    public function findAndLock(string $id): Event;

    public function count(): int;

    /**
     * Every event of the client (id, title, date), e.g. to pick the events a team member sees.
     *
     * @return Collection<int, Event>
     */
    public function listForClient(Client $client): Collection;

    /** @return array<string, int> */
    public function countByState(?Client $client = null): array;
}
