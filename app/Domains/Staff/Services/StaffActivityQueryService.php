<?php

namespace App\Domains\Staff\Services;

use App\Domains\Staff\Contracts\StaffActivityQueryServiceInterface;
use App\Domains\Staff\Contracts\StaffActivityRepositoryInterface;
use App\Domains\Staff\DTOs\ActivityFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StaffActivityQueryService implements StaffActivityQueryServiceInterface
{
    public function __construct(
        private readonly StaffActivityRepositoryInterface $activities,
    ) {}

    public function paginate(ActivityFilters $filters): LengthAwarePaginator
    {
        return $this->activities->paginateFiltered($filters, 50);
    }

    public function forClient(string $clientId, ?string $action = null): Collection
    {
        return $this->activities->forClient($clientId, $action);
    }

    public function actions(): array
    {
        return $this->activities->actions();
    }
}
