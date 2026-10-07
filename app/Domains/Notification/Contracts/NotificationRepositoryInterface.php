<?php

namespace App\Domains\Notification\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Notification\Enums\NotificationKind;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<Notification>
 */
interface NotificationRepositoryInterface extends RepositoryInterface
{
    public function findByProviderMessageId(string $messageId): ?Notification;

    /**
     * The event's messages, newest first; only those with `$status` when given.
     *
     * @return LengthAwarePaginator<int, Notification>
     */
    public function paginateForEvent(string $eventId, int $perPage, ?NotificationStatus $status = null): LengthAwarePaginator;

    /**
     * How many of the event's messages are in each status.
     *
     * @return array<string, int> keyed by NotificationStatus value
     */
    public function statusCountsForEvent(string $eventId): array;

    /**
     * All messages sent to a guest, newest first, with their delivery log.
     *
     * @return Collection<int, Notification>
     */
    public function forGuest(string $guestId): Collection;

    /** Messages of this kind sent to the guest, not counting failed ones. */
    public function countForGuest(string $guestId, NotificationKind $kind): int;

    /** @return LengthAwarePaginator<int, Notification> */
    public function paginateByStatus(NotificationStatus $status, int $perPage): LengthAwarePaginator;

    /**
     * Guests per status of their latest message, by event.
     *
     * @param  list<string>  $eventIds
     * @return array<string, array<string, int>> event id => status => guests
     */
    public function latestStatusCountsForEvents(array $eventIds): array;

    public function countByStatus(NotificationStatus $status): int;

    /** @return Collection<int, string> ids */
    public function stalePendingIds(int $olderThanMinutes, int $limit = 500): Collection;

    /** @param array<string, mixed> $attributes */
    public function recordDelivery(Notification $notification, array $attributes): void;
}
