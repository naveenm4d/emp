<?php

namespace App\Domains\Client\Contracts;

use App\Domains\Client\DTOs\RegisterClientData;
use App\Domains\Client\DTOs\UpdateProfileData;
use App\Domains\Client\Models\Client;

interface ClientServiceInterface
{
    public function register(RegisterClientData $data): Client;

    public function updateProfile(Client $client, UpdateProfileData $data): Client;

    public function updatePassword(Client $client, string $password): void;
}
