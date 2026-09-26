<?php

namespace App\Domains\Rsvp\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\DTOs\RsvpFilters;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface RsvpQueryServiceInterface
{
    /** @return LengthAwarePaginator<int, Rsvp> */
    public function forEvent(Event $event, RsvpFilters $filters): LengthAwarePaginator;

    /** @return array<string, int> */
    public function summary(Event $event): array;

    /** Public RSVP lookup, with event and guest loaded. */
    public function findByToken(string $token): Rsvp;

    /** The guest's latest RSVP link (what their personal link opens), with what the RSVP page needs. */
    public function findLatestForGuest(Guest $guest): ?Rsvp;

    /**
     * All of the guest's RSVP links, newest first (the guest's timeline).
     *
     * @return Collection<int, Rsvp>
     */
    public function forGuest(Guest $guest): Collection;

    public function count(): int;
}
