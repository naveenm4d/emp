<?php

namespace App\Domains\Client\Contracts;

use App\Domains\Client\DTOs\InviteMemberData;
use App\Domains\Client\DTOs\MemberAccessData;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Event\Models\Event;
use Illuminate\Support\Collection;

/**
 * The people who sign in to a client account: inviting them (within the
 * plan's user limit), their permissions and events, and the owner.
 */
interface ClientTeamServiceInterface
{
    /** @return Collection<int, ClientUser> owner first */
    public function members(Client $client): Collection;

    /** Creates the user and emails the invitation. Throws UserLimitReachedException when the account is full. */
    public function invite(Client $client, InviteMemberData $data, ClientUser $by): ClientUser;

    public function updateAccess(ClientUser $member, MemberAccessData $access): ClientUser;

    public function remove(ClientUser $member): void;

    /** Emails a new invitation link to a user who hasn't accepted yet. */
    public function resendInvitation(ClientUser $member, ClientUser $by): void;

    /** The member becomes the owner; the old owner stays as a member with every permission and event. */
    public function transferOwnership(ClientUser $owner, ClientUser $member): void;

    /** Sets the password from an invitation link. Returns null when the link is invalid or expired. */
    public function acceptInvitation(string $email, string $token, string $password): ?ClientUser;

    /** Gives a member access to an event (e.g. one they just created). */
    public function grantEvent(ClientUser $user, Event $event): void;
}
