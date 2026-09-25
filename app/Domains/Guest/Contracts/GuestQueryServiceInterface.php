<?php

namespace App\Domains\Guest\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\DTOs\GuestFilters;
use App\Domains\Guest\Models\Guest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GuestQueryServiceInterface
{
    /** @return LengthAwarePaginator<int, Guest> */
    public function forEvent(Event $event, GuestFilters $filters): LengthAwarePaginator;

    /** @return array<string, int> */
    public function summary(Event $event): array;

    public function count(): int;
}
