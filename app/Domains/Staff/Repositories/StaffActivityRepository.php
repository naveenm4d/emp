<?php

namespace App\Domains\Staff\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Staff\Contracts\StaffActivityRepositoryInterface;
use App\Domains\Staff\DTOs\ActivityFilters;
use App\Domains\Staff\Models\StaffActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<StaffActivity>
 */
class StaffActivityRepository extends BaseRepository implements StaffActivityRepositoryInterface
{
    protected function model(): string
    {
        return StaffActivity::class;
    }

    public function paginateFiltered(ActivityFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with(['staffMember', 'client'])
            ->when($filters->staffMemberId, fn ($q, string $id) => $q->where('staff_member_id', $id))
            ->when($filters->clientId, fn ($q, string $id) => $q->where('client_id', $id))
            ->when($filters->action, fn ($q, string $action) => $q->where('action', $action))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function forClient(string $clientId, ?string $action = null, int $limit = 50): Collection
    {
        return $this->query()
            ->with('staffMember')
            ->where('client_id', $clientId)
            ->when($action, fn ($q, string $action) => $q->where('action', $action))
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function actions(): array
    {
        /** @var list<string> */
        return $this->query()->distinct()->orderBy('action')->pluck('action')->all();
    }
}
