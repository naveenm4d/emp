<?php

namespace App\Domains\Client\Services;

use App\Core\Services\BaseService;
use App\Domains\Client\Contracts\ClientRepositoryInterface;
use App\Domains\Client\Contracts\ClientServiceInterface;
use App\Domains\Client\Contracts\ClientUserRepositoryInterface;
use App\Domains\Client\DTOs\RegisterClientData;
use App\Domains\Client\DTOs\UpdateProfileData;
use App\Domains\Client\Enums\ClientUserRole;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Auth\Events\Registered;

class ClientService extends BaseService implements ClientServiceInterface
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly ClientUserRepositoryInterface $users,
    ) {}

    public function register(RegisterClientData $data): ClientUser
    {
        $owner = $this->transaction(function () use ($data) {
            /** @var Client $client */
            $client = $this->clients->create(['name' => $data->name, 'email' => $data->email]);

            /** @var ClientUser */
            return $this->users->create([
                'client_id' => $client->id,
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'role' => ClientUserRole::Owner,
                'all_events' => true,
                'joined_at' => now(),
            ]);
        });

        event(new Registered($owner));

        return $owner;
    }

    public function updateAccount(Client $client, UpdateProfileData $data): Client
    {
        /** @var Client */
        return $this->clients->update($client, $data->toArray());
    }

    public function updateProfile(ClientUser $user, UpdateProfileData $data): ClientUser
    {
        $attributes = $data->toArray();

        if (isset($attributes['email']) && $attributes['email'] !== $user->email) {
            $attributes['email_verified_at'] = null;
        }

        /** @var ClientUser */
        return $this->users->update($user, $attributes);
    }

    public function updatePassword(ClientUser $user, string $password): void
    {
        $this->users->update($user, ['password' => $password]);
    }

    public function recordLogin(ClientUser $user): void
    {
        $this->users->update($user, ['last_login_at' => now()]);
    }
}
