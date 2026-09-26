<?php

namespace App\Domains\Seating\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Seating\Contracts\SeatAssignmentRepositoryInterface;
use App\Domains\Seating\Models\SeatAssignment;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<SeatAssignment>
 */
class SeatAssignmentRepository extends BaseRepository implements SeatAssignmentRepositoryInterface
{
    protected function model(): string
    {
        return SeatAssignment::class;
    }

    public function forGuest(string $guestId): Collection
    {
        return $this->query()->where('guest_id', $guestId)->orderBy('party_member')->get();
    }

    public function takenSeats(string $tableId): array
    {
        /** @var list<int> */
        return $this->query()->where('table_id', $tableId)->pluck('seat_number')->map(fn ($seat) => (int) $seat)->all();
    }

    public function releaseGuest(string $guestId): void
    {
        $this->query()->where('guest_id', $guestId)->delete();
    }

    public function releaseMember(string $guestId, int $partyMember): void
    {
        $this->query()->where('guest_id', $guestId)->where('party_member', $partyMember)->delete();
    }

    public function releaseMembersFrom(string $guestId, int $partyMember): void
    {
        $this->query()->where('guest_id', $guestId)->where('party_member', '>=', $partyMember)->delete();
    }
}
