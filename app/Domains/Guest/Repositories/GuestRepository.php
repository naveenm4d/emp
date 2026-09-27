<?php

namespace App\Domains\Guest\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Guest\Contracts\GuestRepositoryInterface;
use App\Domains\Guest\DTOs\GuestFilters;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Models\Guest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Guest>
 */
class GuestRepository extends BaseRepository implements GuestRepositoryInterface
{
    protected function model(): string
    {
        return Guest::class;
    }

    public function paginateForEvent(string $eventId, GuestFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->where('event_id', $eventId)
            ->with(['link.event', 'latestRsvp.latestNotification', 'latestRsvp.guest.link.event', 'seats.table'])
            ->withCount(Guest::messagesSentCounts())
            ->when($filters->source, fn (Builder $q, $v) => $q->where('source', $v))
            ->when($filters->approvalStatus, fn (Builder $q, $v) => $q->where('approval_status', $v))
            ->when($filters->rsvpStatus, fn (Builder $q, $v) => $q->where('rsvp_status', $v))
            ->when($filters->search, fn (Builder $q, string $search) => $q->where(
                fn (Builder $q) => $q
                    ->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('email', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('phone', "%{$search}%"),
            ))
            // Alphabetical, with the id as a tiebreaker so rows never shift after an update.
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countActive(string $eventId): int
    {
        return $this->query()
            ->where('event_id', $eventId)
            ->whereIn('approval_status', ApprovalStatus::capacityStatuses())
            ->count();
    }

    public function approveAllForEvent(string $eventId): int
    {
        return $this->query()
            ->where('event_id', $eventId)
            ->where('approval_status', '!=', ApprovalStatus::Approved)
            ->update(['approval_status' => ApprovalStatus::Approved, 'approval_status_changed_at' => now()]);
    }

    public function emailExists(string $eventId, string $email, ?string $exceptId = null): bool
    {
        return $this->query()
            ->where('event_id', $eventId)
            ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
            ->when($exceptId, fn (Builder $q, string $id) => $q->whereKeyNot($id))
            ->exists();
    }

    public function phoneExists(string $eventId, string $phone, ?string $exceptId = null): bool
    {
        return $this->query()
            ->where('event_id', $eventId)
            ->where('phone', $phone)
            ->when($exceptId, fn (Builder $q, string $id) => $q->whereKeyNot($id))
            ->exists();
    }

    public function summary(string $eventId): array
    {
        $select = ['count(*) as total'];
        $bindings = [];

        foreach (ApprovalStatus::cases() as $status) {
            $select[] = "count(*) filter (where approval_status = ?) as {$status->value}";
            $bindings[] = $status->value;
        }

        foreach (GuestRsvpStatus::cases() as $status) {
            $select[] = "count(*) filter (where rsvp_status = ?) as rsvp_{$status->value}";
            $bindings[] = $status->value;
        }

        // People expected: confirmed guests with their plus-ones and children.
        $select[] = 'coalesce(sum(1 + additional_guests + children) filter (where rsvp_status = ?), 0) as headcount';
        $bindings[] = GuestRsvpStatus::Confirmed->value;

        // Who makes up that headcount besides the guests themselves.
        $select[] = 'coalesce(sum(additional_guests) filter (where rsvp_status = ?), 0) as confirmed_additional';
        $select[] = 'coalesce(sum(children) filter (where rsvp_status = ?), 0) as confirmed_children';
        array_push($bindings, GuestRsvpStatus::Confirmed->value, GuestRsvpStatus::Confirmed->value);

        // Everyone holding a seat (pending or approved) with their party, whether or not they replied.
        $capacity = ApprovalStatus::capacityStatuses();
        $select[] = 'coalesce(sum(1 + additional_guests + children) filter (where approval_status in ('.implode(', ', array_fill(0, count($capacity), '?')).') and rsvp_status <> ?), 0) as headcount_total';
        array_push($bindings, ...array_map(fn (ApprovalStatus $status) => $status->value, $capacity));
        $bindings[] = GuestRsvpStatus::Declined->value;

        $row = $this->query()
            ->toBase()
            ->where('event_id', $eventId)
            ->selectRaw(implode(', ', $select), $bindings)
            ->first();

        return array_map('intval', (array) $row);
    }

    public function approvedWithSeats(string $eventId): Collection
    {
        return $this->query()
            ->where('event_id', $eventId)
            ->where('approval_status', ApprovalStatus::Approved)
            ->with('seats.table')
            ->orderBy('name')
            ->get();
    }
}
