<?php

namespace App\Domains\Guest\Services;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Contracts\GuestQueryServiceInterface;
use App\Domains\Guest\Contracts\GuestRepositoryInterface;
use App\Domains\Guest\DTOs\GuestFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class GuestQueryService implements GuestQueryServiceInterface
{
    public function __construct(
        private readonly GuestRepositoryInterface $guests,
    ) {}

    public function forEvent(Event $event, GuestFilters $filters): LengthAwarePaginator
    {
        return $this->guests->paginateForEvent($event->id, $filters, config('emp.per_page'));
    }

    public function summary(Event $event): array
    {
        return $this->guests->summary($event->id);
    }

    public function approvedWithSeats(Event $event): Collection
    {
        return $this->guests->approvedWithSeats($event->id);
    }

    public function count(): int
    {
        return $this->guests->count();
    }
}
