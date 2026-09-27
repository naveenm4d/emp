<?php

namespace App\Domains\Guest\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\DTOs\GuestFilters;
use App\Domains\Guest\Models\Guest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface GuestQueryServiceInterface
{
    /** @return LengthAwarePaginator<int, Guest> */
    public function forEvent(Event $event, GuestFilters $filters): LengthAwarePaginator;

    /**
     * The first few approved guests who were sent their link and haven't replied.
     *
     * @return LengthAwarePaginator<int, Guest>
     */
    public function waitingForEvent(Event $event, int $limit): LengthAwarePaginator;

    /** @return array<string, int> */
    public function summary(Event $event): array;

    /** @return Collection<int, Guest> approved guests of the event, by name, with their seats */
    public function approvedWithSeats(Event $event): Collection;

    public function count(): int;
}
