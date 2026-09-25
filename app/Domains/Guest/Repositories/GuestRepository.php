<?php

namespace App\Domains\Guest\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Guest\Contracts\GuestRepositoryInterface;
use App\Domains\Guest\DTOs\GuestFilters;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\RsvpStatus;
use App\Domains\Guest\Models\Guest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
            ->with('latestInvitation')
            ->when($filters->source, fn (Builder $q, $v) => $q->where('source', $v))
            ->when($filters->approvalStatus, fn (Builder $q, $v) => $q->where('approval_status', $v))
            ->when($filters->rsvpStatus, fn (Builder $q, $v) => $q->where('rsvp_status', $v))
            ->when($filters->search, fn (Builder $q, string $search) => $q->where(
                fn (Builder $q) => $q
                    ->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('email', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('phone', "%{$search}%"),
            ))
            ->oldest()
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

        foreach ([RsvpStatus::Confirmed, RsvpStatus::Declined] as $status) {
            $select[] = "count(*) filter (where rsvp_status = ?) as rsvp_{$status->value}";
            $bindings[] = $status->value;
        }

        $row = $this->query()
            ->toBase()
            ->where('event_id', $eventId)
            ->selectRaw(implode(', ', $select), $bindings)
            ->first();

        return array_map('intval', (array) $row);
    }
}
