<?php

namespace App\Domains\Notification\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Notification\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationQueryServiceInterface
{
    /** @return LengthAwarePaginator<int, Notification> */
    public function forEvent(Event $event): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, Notification> */
    public function failed(): LengthAwarePaginator;

    public function count(): int;

    public function countFailed(): int;
}
