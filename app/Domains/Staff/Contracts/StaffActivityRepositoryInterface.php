<?php

namespace App\Domains\Staff\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Staff\DTOs\ActivityFilters;
use App\Domains\Staff\Models\StaffActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<StaffActivity>
 */
interface StaffActivityRepositoryInterface extends RepositoryInterface
{
    /** @return LengthAwarePaginator<int, StaffActivity> newest first */
    public function paginateFiltered(ActivityFilters $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  string|list<string>|null  $action  only these actions
     * @return Collection<int, StaffActivity> newest first
     */
    public function forClient(string $clientId, string|array|null $action = null, int $limit = 50): Collection;

    /** @return list<string> actions that were logged, for the filter */
    public function actions(): array;
}
