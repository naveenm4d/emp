<?php

namespace App\Domains\Client\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Client\DTOs\ClientFilters;
use App\Domains\Client\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<Client>
 */
interface ClientRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?Client;

    /** @return LengthAwarePaginator<int, Client> */
    public function search(ClientFilters $filters, int $perPage): LengthAwarePaginator;
}
