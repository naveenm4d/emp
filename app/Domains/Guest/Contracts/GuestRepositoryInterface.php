<?php

namespace App\Domains\Guest\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Guest\DTOs\GuestFilters;
use App\Domains\Guest\Models\Guest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<Guest>
 */
interface GuestRepositoryInterface extends RepositoryInterface
{
    /** @return LengthAwarePaginator<int, Guest> */
    public function paginateForEvent(string $eventId, GuestFilters $filters, int $perPage): LengthAwarePaginator;

    /** Guests occupying a seat (pending + approved). */
    public function countActive(string $eventId): int;

    /** Sets every not-yet-approved guest of the event to approved; returns how many changed. */
    public function approveAllForEvent(string $eventId): int;

    public function emailExists(string $eventId, string $email, ?string $exceptId = null): bool;

    public function phoneExists(string $eventId, string $phone, ?string $exceptId = null): bool;

    /** @return array<string, int> */
    public function summary(string $eventId): array;
}
