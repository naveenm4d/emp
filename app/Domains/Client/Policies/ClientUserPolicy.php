<?php

namespace App\Domains\Client\Policies;

use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Models\ClientUser;

/**
 * Managing the people of a client account. Nobody changes the owner or
 * themselves; only the owner hands the account over.
 */
class ClientUserPolicy
{
    public function viewAny(ClientUser $user): bool
    {
        return $user->hasPermission(ClientPermission::TeamManage);
    }

    public function create(ClientUser $user): bool
    {
        return $user->hasPermission(ClientPermission::TeamManage);
    }

    public function update(ClientUser $user, ClientUser $member): bool
    {
        return $user->hasPermission(ClientPermission::TeamManage)
            && $member->client_id === $user->client_id
            && ! $member->isOwner()
            && ! $member->is($user);
    }

    public function delete(ClientUser $user, ClientUser $member): bool
    {
        return $this->update($user, $member);
    }

    public function transferOwnership(ClientUser $user, ClientUser $member): bool
    {
        return $user->isOwner()
            && $member->client_id === $user->client_id
            && ! $member->is($user);
    }
}
