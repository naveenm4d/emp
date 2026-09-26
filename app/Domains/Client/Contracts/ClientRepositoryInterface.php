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

    /** Loads the client with a row lock. Must be called inside a transaction. */
    public function findAndLock(string $id): Client;

    /** Every event the client ever created, deleted ones included. */
    public function eventsCreatedCount(string $clientId): int;

    /** @return LengthAwarePaginator<int, Client> */
    public function search(ClientFilters $filters, int $perPage): LengthAwarePaginator;
}
