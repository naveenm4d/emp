<?php

namespace App\Domains\Rsvp\Services;

use App\Core\Exceptions\NotFoundException;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use App\Domains\Rsvp\Contracts\RsvpRepositoryInterface;
use App\Domains\Rsvp\DTOs\RsvpFilters;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class RsvpQueryService implements RsvpQueryServiceInterface
{
    public function __construct(
        private readonly RsvpRepositoryInterface $rsvps,
    ) {}

    public function forEvent(Event $event, RsvpFilters $filters): LengthAwarePaginator
    {
        return $this->rsvps->paginateForEvent($event->id, $filters, config('emp.per_page'));
    }

    public function summary(Event $event): array
    {
        return $this->rsvps->summary($event->id);
    }

    public function findByToken(string $token): Rsvp
    {
        $rsvp = $this->rsvps->findByToken($token)
            ?? throw new NotFoundException('RSVP link not found.');

        return $rsvp->load(['event.templateVersion.template', 'event.media', 'guest']);
    }

    public function forGuest(Guest $guest): Collection
    {
        return $this->rsvps->forGuest($guest->id);
    }

    public function findLatestForGuest(Guest $guest): ?Rsvp
    {
        return $this->rsvps->findLatestForGuest($guest->id)
            ?->load(['event.templateVersion.template', 'event.media', 'guest']);
    }

    public function count(): int
    {
        return $this->rsvps->count();
    }
}
