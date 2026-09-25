<?php

namespace App\Domains\Client\Services;

use App\Core\Services\BaseService;
use App\Domains\Client\Contracts\ClientRepositoryInterface;
use App\Domains\Client\Contracts\ClientServiceInterface;
use App\Domains\Client\DTOs\RegisterClientData;
use App\Domains\Client\DTOs\UpdateProfileData;
use App\Domains\Client\Models\Client;
use Illuminate\Auth\Events\Registered;

class ClientService extends BaseService implements ClientServiceInterface
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
    ) {}

    public function register(RegisterClientData $data): Client
    {
        /** @var Client $client */
        $client = $this->clients->create($data->toArray());

        event(new Registered($client));

        return $client;
    }

    public function updateProfile(Client $client, UpdateProfileData $data): Client
    {
        $attributes = $data->toArray();

        if (isset($attributes['email']) && $attributes['email'] !== $client->email) {
            $attributes['email_verified_at'] = null;
        }

        /** @var Client */
        return $this->clients->update($client, $attributes);
    }

    public function updatePassword(Client $client, string $password): void
    {
        $this->clients->update($client, ['password' => $password]);
    }
}
