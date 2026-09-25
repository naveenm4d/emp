<?php

namespace App\Domains\Client\Services;

use App\Domains\Client\Contracts\ClientQueryServiceInterface;
use App\Domains\Client\Contracts\ClientRepositoryInterface;
use App\Domains\Client\DTOs\ClientFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientQueryService implements ClientQueryServiceInterface
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
    ) {}

    public function search(ClientFilters $filters): LengthAwarePaginator
    {
        return $this->clients->search($filters, config('emp.per_page'));
    }

    public function count(): int
    {
        return $this->clients->count();
    }
}
