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

    /** @return LengthAwarePaginator<int, Notification> */
    public function paginateForEvent(string $eventId, int $perPage): LengthAwarePaginator;

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

    public function countByStatus(NotificationStatus $status): int;

    /** @return Collection<int, string> ids */
    public function stalePendingIds(int $olderThanMinutes, int $limit = 500): Collection;

    /** @param array<string, mixed> $attributes */
    public function recordDelivery(Notification $notification, array $attributes): void;
}
