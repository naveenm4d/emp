<?php

namespace App\Domains\Event\Repositories;

use App\Core\Exceptions\NotFoundException;
use App\Core\Repositories\BaseRepository;
use App\Domains\Event\Contracts\EventRepositoryInterface;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Enums\EventPeriod;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

/**
 * @extends BaseRepository<Event>
 */
class EventRepository extends BaseRepository implements EventRepositoryInterface
{
    protected function model(): string
    {
        return Event::class;
    }

    public function findBySlug(string $slug): ?Event
    {
        return $this->query()->where('slug', mb_strtolower(trim($slug)))->latest()->first();
    }

    public function findAndLock(string $id): Event
    {
        return $this->query()->lockForUpdate()->find($id)
            ?? throw new NotFoundException('Event not found.');
    }

    public function paginateForClient(string $clientId, EventFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->ordered($this->withResponseCounts($this->filtered($filters)), $filters)
            ->where('client_id', $clientId)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateAll(EventFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->ordered($this->withResponseCounts($this->filtered($filters)), $filters)
            ->with(['client:id,name,email,plan', 'publicLink'])
            ->paginate($perPage)
            ->withQueryString();
    }

    public function totalsForClient(string $clientId): array
    {
        $upcoming = $this->upcoming($this->query()->where('client_id', $clientId));

        // Approved guests of upcoming events who were sent their link and haven't answered.
        $waiting = $this->upcoming($this->query()->where('client_id', $clientId))
            ->toBase()
            ->join('guests', 'guests.event_id', '=', 'events.id')
            ->where('guests.approval_status', ApprovalStatus::Approved->value)
            ->where('guests.rsvp_status', GuestRsvpStatus::Pending->value)
            ->count();

        return [
            'upcoming' => $upcoming->count(),
            'drafts' => $this->query()->where('client_id', $clientId)->where('state', EventState::Draft)->count(),
            'waiting' => $waiting,
        ];
    }

    public function countByState(?string $clientId = null): array
    {
        $counts = $this->query()
            ->when($clientId, fn (Builder $query, string $id) => $query->where('client_id', $id))
            ->toBase()
            ->selectRaw('state, count(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        return collect(EventState::values())
            ->mapWithKeys(fn (string $state) => [$state => (int) ($counts[$state] ?? 0)])
            ->all();
    }

    public function recordRender(Event $event, string $path, string $hash): void
    {
        $attributes = ['rendered_path' => $path, 'rendered_hash' => $hash, 'rendered_at' => now()];

        $this->query()->whereKey($event->id)->toBase()->update($attributes);

        $event->forceFill($attributes)->syncOriginalAttributes(array_keys($attributes));
    }

    /**
     * Guest counts behind the response bars: all guests, replies, parties
     * still waiting on a reply, registrations to approve and the expected
     * headcount (confirmed guests with their party).
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    private function withResponseCounts(Builder $query): Builder
    {
        return $query
            ->withCount([
                'guests',
                'guests as attending_count' => fn (Builder $guests) => $guests->where('rsvp_status', GuestRsvpStatus::Confirmed),
                'guests as declined_count' => fn (Builder $guests) => $guests->where('rsvp_status', GuestRsvpStatus::Declined),
                'guests as waiting_count' => fn (Builder $guests) => $guests
                    ->where('approval_status', ApprovalStatus::Approved)
                    ->where('rsvp_status', GuestRsvpStatus::Pending),
                'guests as to_approve_count' => fn (Builder $guests) => $guests->where('approval_status', ApprovalStatus::Pending),
            ])
            ->withSum(
                ['guests as headcount' => fn (Builder $guests) => $guests->where('rsvp_status', GuestRsvpStatus::Confirmed)],
                new Expression('1 + additional_guests + children'),
            );
    }

    /**
     * Upcoming events soonest first (undated drafts last), past events most
     * recent first, anything else newest first.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    private function ordered(Builder $query, EventFilters $filters): Builder
    {
        return match ($filters->period) {
            EventPeriod::Upcoming => $query->orderByRaw('event_date asc nulls last')->latest(),
            EventPeriod::Past => $query->orderByDesc('event_date')->latest(),
            null => $query->latest(),
        };
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    private function upcoming(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereNull('event_date')
            ->orWhereDate('event_date', '>=', today()));
    }

    /** @return Builder<Event> */
    private function filtered(EventFilters $filters): Builder
    {
        return $this->query()
            ->when($filters->period === EventPeriod::Upcoming, fn (Builder $query) => $this->upcoming($query))
            ->when($filters->period === EventPeriod::Past, fn (Builder $query) => $query->whereDate('event_date', '<', today()))
            ->when($filters->state, fn (Builder $query, EventState $state) => $query->where('state', $state))
            ->when($filters->registrationType, fn (Builder $query, RegistrationType $type) => $query->where('registration_type', $type))
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('title', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('slug', "%{$search}%", caseSensitive: false),
            ));
    }
}
