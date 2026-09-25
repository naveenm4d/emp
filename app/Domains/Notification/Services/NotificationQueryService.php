<?php

namespace App\Domains\Notification\Services;

use App\Domains\Event\Models\Event;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Notification\Contracts\NotificationRepositoryInterface;
use App\Domains\Notification\Enums\NotificationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationQueryService implements NotificationQueryServiceInterface
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    public function forEvent(Event $event): LengthAwarePaginator
    {
        return $this->notifications->paginateForEvent($event->id, config('emp.per_page'));
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
}
