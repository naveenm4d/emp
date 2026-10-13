<?php

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\DTOs\ActivityFilters;
use App\Domains\Staff\Models\StaffActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StaffActivityQueryServiceInterface
{
    /** @return LengthAwarePaginator<int, StaffActivity> */
    public function paginate(ActivityFilters $filters): LengthAwarePaginator;

    /**
     * @param  string|list<string>|null  $action  only these actions
     * @return Collection<int, StaffActivity> the client's history, newest first
     */
    public function forClient(string $clientId, string|array|null $action = null): Collection;

    /** @return list<string> */
    public function actions(): array;
}
