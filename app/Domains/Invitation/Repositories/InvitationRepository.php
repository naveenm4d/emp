<?php

namespace App\Domains\Invitation\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Invitation\Contracts\InvitationRepositoryInterface;
use App\Domains\Invitation\DTOs\InvitationFilters;
use App\Domains\Invitation\Enums\InvitationStatus;
use App\Domains\Invitation\Models\Invitation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Invitation>
 */
class InvitationRepository extends BaseRepository implements InvitationRepositoryInterface
{
    protected function model(): string
    {
        return Invitation::class;
    }

    public function findByToken(string $token): ?Invitation
    {
        return $this->query()->where('token', $token)->first();
    }

    public function findActiveForGuest(string $guestId): ?Invitation
    {
        return $this->query()
            ->where('guest_id', $guestId)
            ->whereIn('status', [InvitationStatus::Pending, InvitationStatus::Sent])
            ->first();
    }

    public function paginateForEvent(string $eventId, InvitationFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->where('event_id', $eventId)
            ->with('guest')
            ->when($filters->status, fn (Builder $q, InvitationStatus $status) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function summary(string $eventId): array
    {
        $select = ['count(*) as total'];
        $bindings = [];

        foreach (InvitationStatus::cases() as $status) {
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
}
