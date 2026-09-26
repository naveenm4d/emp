<?php

namespace App\Domains\Rsvp\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Rsvp\DTOs\RsvpFilters;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Rsvp>
 */
interface RsvpRepositoryInterface extends RepositoryInterface
{
    public function findByToken(string $token): ?Rsvp;

    /** Reloads the link with a row lock. Must be called inside a transaction. */
    public function findAndLock(string $id): Rsvp;

    public function findActiveForGuest(string $guestId): ?Rsvp;

    /** The guest's most recent RSVP link. */
    public function findLatestForGuest(string $guestId): ?Rsvp;

    /**
     * All of the guest's RSVP links, newest first.
     *
     * @return Collection<int, Rsvp>
     */
    public function forGuest(string $guestId): Collection;

    /** @return LengthAwarePaginator<int, Rsvp> */
    public function paginateForEvent(string $eventId, RsvpFilters $filters, int $perPage): LengthAwarePaginator;

    /** @return array<string, int> */
    public function summary(string $eventId): array;

    /**
     * Sent, unexpired links of published events with automatic reminders on,
     * whose guest has a phone and that still miss at least one automatic reminder.
     *
     * @return Collection<int, Rsvp>
     */
    public function candidatesForAutoReminder(): Collection;
}
