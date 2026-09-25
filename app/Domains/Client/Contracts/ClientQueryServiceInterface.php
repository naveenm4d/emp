<?php

namespace App\Domains\Client\Contracts;

use App\Domains\Client\DTOs\ClientFilters;
use App\Domains\Client\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClientQueryServiceInterface
{
    /** @return LengthAwarePaginator<int, Client> */
    public function search(ClientFilters $filters): LengthAwarePaginator;

    public function count(): int;
}
