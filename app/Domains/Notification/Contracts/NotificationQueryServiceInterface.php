<?php

namespace App\Domains\Notification\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface NotificationQueryServiceInterface
{
    /** @return LengthAwarePaginator<int, Notification> */
    public function forEvent(Event $event): LengthAwarePaginator;

    /** @return LengthAwarePaginator<int, Notification> */
    public function failed(): LengthAwarePaginator;

    /**
     * Every message sent to the guest, newest first (the guest's timeline).
     *
     * @return Collection<int, Notification>
     */
    public function forGuest(Guest $guest): Collection;

    public function count(): int;

    public function countFailed(): int;
}
