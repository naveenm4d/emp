<?php

namespace App\Domains\Rsvp\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Event\Enums\EventState;
use App\Domains\Rsvp\Contracts\RsvpRepositoryInterface;
use App\Domains\Rsvp\DTOs\RsvpFilters;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Rsvp>
 */
class RsvpRepository extends BaseRepository implements RsvpRepositoryInterface
{
    protected function model(): string
    {
        return Rsvp::class;
    }

    public function findByToken(string $token): ?Rsvp
    {
        return $this->query()->where('token', $token)->first();
    }

    public function findActiveForGuest(string $guestId): ?Rsvp
    {
        return $this->query()
            ->where('guest_id', $guestId)
            ->whereIn('status', [RsvpStatus::Pending, RsvpStatus::Sent])
            ->first();
    }

    public function findLatestForGuest(string $guestId): ?Rsvp
    {
        return $this->query()->where('guest_id', $guestId)->latest()->first();
    }

    public function forGuest(string $guestId): Collection
    {
        return $this->query()->where('guest_id', $guestId)->with('guest.link.event')->latest()->get();
    }

    public function paginateForEvent(string $eventId, RsvpFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->where('event_id', $eventId)
            ->with(['guest.link.event', 'event', 'latestNotification'])
            ->when($filters->status, fn (Builder $q, RsvpStatus $status) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function summary(string $eventId): array
    {
        $select = ['count(*) as total'];
        $bindings = [];

        foreach (RsvpStatus::cases() as $status) {
            $select[] = "count(*) filter (where status = ?) as {$status->value}";
            $bindings[] = $status->value;
        }

        $row = $this->query()
            ->toBase()
            ->where('event_id', $eventId)
            ->selectRaw(implode(', ', $select), $bindings)
            ->first();

        return array_map('intval', (array) $row);
    }

    public function candidatesForAutoReminder(): Collection
    {
        return $this->query()
            ->where('status', RsvpStatus::Sent)
            ->where('expires_at', '>', now())
            ->where(fn (Builder $q) => $q->whereNull('auto_after_reminded_at')->orWhereNull('auto_before_reminded_at'))
            ->whereHas('event', fn (Builder $q) => $q->where('auto_reminders', true)->where('state', EventState::Published))
            ->whereHas('guest', fn (Builder $q) => $q->whereNotNull('phone'))
            ->with(['event', 'guest'])
            ->get();
    }
}
