<?php

namespace App\Domains\Client\Contracts;

use App\Domains\Client\DTOs\CreateClientData;
use App\Domains\Client\DTOs\RegisterClientData;
use App\Domains\Client\DTOs\UpdateProfileData;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;

interface ClientServiceInterface
{
    /** Creates the account and its owner, who is returned to sign in. */
    public function register(RegisterClientData $data): ClientUser;

    /**
     * Staff create the account and its owner (on the default plan). With a
     * password the owner can sign in at once; without one they're emailed an invitation.
     */
    public function create(CreateClientData $data): Client;

    /** The account's name and contact email (staff). */
    public function updateAccount(Client $client, UpdateProfileData $data): Client;

    /** A user's own name and email; a new email has to be verified again. */
    public function updateProfile(ClientUser $user, UpdateProfileData $data): ClientUser;

    public function updatePassword(ClientUser $user, string $password): void;

    public function recordLogin(ClientUser $user): void;
}
