<?php

namespace App\Domains\Event\Services;

use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Contracts\EventRepositoryInterface;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Enums\EventPeriod;
use App\Domains\Event\Models\Event;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EventQueryService implements EventQueryServiceInterface
{
    /** Most events the event switcher lists. */
    private const SWITCHER_LIMIT = 50;

    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly NotificationQueryServiceInterface $notifications,
    ) {}

    public function forClient(Client $client, EventFilters $filters, ?ClientUser $viewer = null): LengthAwarePaginator
    {
        return $this->withMessageIssues(
            $this->events->paginateForClient($client->id, $filters, config('emp.per_page'), $this->memberId($viewer)),
        );
    }

    public function forSwitcher(ClientUser $viewer): LengthAwarePaginator
    {
        return $this->withMessageIssues(
            $this->events->paginateForClient($viewer->client_id, new EventFilters(period: EventPeriod::Upcoming), self::SWITCHER_LIMIT, $this->memberId($viewer)),
        );
    }

    public function totalsForClient(Client $client, ?ClientUser $viewer = null): array
    {
        return $this->events->totalsForClient($client->id, $this->memberId($viewer));
    }

    public function listForClient(Client $client): Collection
    {
        return $this->events->listForClient($client->id);
    }

    public function all(EventFilters $filters): LengthAwarePaginator
    {
        return $this->withMessageIssues($this->events->paginateAll($filters, config('emp.per_page')));
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

    /** The member whose events to list, or null for every event of the account. */
    private function memberId(?ClientUser $viewer): ?string
    {
        return $viewer === null || $viewer->seesAllEvents() ? null : $viewer->id;
    }

    /**
     * Sets `failed_messages_count` / `queued_messages_count` on each listed
     * event: guests whose latest message failed or hasn't gone out yet.
     *
     * @param  LengthAwarePaginator<int, Event>  $events
     * @return LengthAwarePaginator<int, Event>
     */
    private function withMessageIssues(LengthAwarePaginator $events): LengthAwarePaginator
    {
        $listed = $events->items();
        $issues = $this->notifications->messageIssuesForEvents(array_values(array_map(fn (Event $event) => $event->id, $listed)));

        array_walk($listed, fn (Event $event) => $event->forceFill([
            'failed_messages_count' => $issues[$event->id]['failed'] ?? 0,
            'queued_messages_count' => $issues[$event->id]['queued'] ?? 0,
        ]));

        return $events;
    }
}
