<?php

namespace App\Domains\Notification\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Enums\NotificationKind;
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

    /** Invitations or reminders sent to the guest, not counting failed ones (for the per-guest limits). */
    public function countSentToGuest(string $guestId, NotificationKind $kind): int;

    public function count(): int;

    public function countFailed(): int;

    /**
     * How many guests' latest message failed or is still queued, per event.
     *
     * @param  list<string>  $eventIds
     * @return array<string, array{failed: int, queued: int}>
     */
    public function messageIssuesForEvents(array $eventIds): array;
}
