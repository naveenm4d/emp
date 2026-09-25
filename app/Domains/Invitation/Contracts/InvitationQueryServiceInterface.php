<?php

namespace App\Domains\Invitation\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Invitation\DTOs\InvitationFilters;
use App\Domains\Invitation\Models\Invitation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvitationQueryServiceInterface
{
    /** @return LengthAwarePaginator<int, Invitation> */
    public function forEvent(Event $event, InvitationFilters $filters): LengthAwarePaginator;

    /** @return array<string, int> */
    public function summary(Event $event): array;

    /** Public RSVP lookup, with event and guest loaded. */
    public function findByToken(string $token): Invitation;

    public function count(): int;
}
