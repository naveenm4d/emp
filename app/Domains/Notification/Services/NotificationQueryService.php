<?php

namespace App\Domains\Notification\Services;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Notification\Contracts\NotificationRepositoryInterface;
use App\Domains\Notification\Enums\NotificationKind;
use App\Domains\Notification\Enums\NotificationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NotificationQueryService implements NotificationQueryServiceInterface
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    public function forEvent(Event $event): LengthAwarePaginator
    {
        return $this->notifications->paginateForEvent($event->id, config('emp.per_page'));
    }

    public function countSentToGuest(string $guestId, NotificationKind $kind): int
    {
        return $this->notifications->countForGuest($guestId, $kind);
    }

    public function forGuest(Guest $guest): Collection
    {
        return $this->notifications->forGuest($guest->id);
    }

    public function failed(): LengthAwarePaginator
    {
        return $this->notifications->paginateByStatus(NotificationStatus::Failed, config('emp.per_page'));
    }

    public function count(): int
    {
        return $this->notifications->count();
    }

    public function countFailed(): int
    {
        return $this->notifications->countByStatus(NotificationStatus::Failed);
    }

    public function messageIssuesForEvents(array $eventIds): array
    {
        return array_map(fn (array $statuses) => [
            'failed' => $statuses[NotificationStatus::Failed->value] ?? 0,
            'queued' => $statuses[NotificationStatus::Pending->value] ?? 0,
        ], $this->notifications->latestStatusCountsForEvents($eventIds));
    }
}
