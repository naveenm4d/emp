<?php

namespace App\Domains\Client\Services;

use App\Core\Exceptions\ConflictException;
use App\Core\Services\BaseService;
use App\Domains\Client\Contracts\ClientRepositoryInterface;
use App\Domains\Client\Contracts\ClientTeamServiceInterface;
use App\Domains\Client\Contracts\ClientUserRepositoryInterface;
use App\Domains\Client\DTOs\InviteMemberData;
use App\Domains\Client\DTOs\MemberAccessData;
use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Enums\ClientUserRole;
use App\Domains\Client\Exceptions\UserLimitReachedException;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Client\Notifications\ClientInvitation;
use App\Domains\Event\Models\Event;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ClientTeamService extends BaseService implements ClientTeamServiceInterface
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly ClientUserRepositoryInterface $users,
    ) {}

    public function members(Client $client): Collection
    {
        return $this->users->forClient($client->id);
    }

    public function invite(Client $client, InviteMemberData $data, ClientUser $by): ClientUser
    {
        $member = $this->transaction(function () use ($client, $data) {
            // The lock keeps two invitations at once from going over the limit.
            $locked = $this->clients->findAndLock($client->id);
            $limit = $locked->userLimit();

            if ($limit !== null && $this->users->countForClient($locked->id) >= $limit) {
                throw new UserLimitReachedException(
                    "Your {$locked->plan->label()} plan includes {$limit} ".Str::plural('user', $limit).'. Remove someone or upgrade to invite more.',
                );
            }

            /** @var ClientUser $member */
            $member = $this->users->create([
                'client_id' => $locked->id,
                'name' => $data->name,
                'email' => $data->email,
                'role' => ClientUserRole::Member,
                'permissions' => $data->access->permissionValues(),
                'all_events' => $data->access->allEvents,
                'invited_at' => now(),
            ]);

            $this->users->syncEvents($member, $data->access->eventIds);

            return $member;
        });

        $this->sendInvitation($member, $client, $by);

        return $member;
    }

    public function updateAccess(ClientUser $member, MemberAccessData $access): ClientUser
    {
        return $this->transaction(function () use ($member, $access) {
            /** @var ClientUser $member */
            $member = $this->users->update($member, [
                'permissions' => $access->permissionValues(),
                'all_events' => $access->allEvents,
            ]);

            $this->users->syncEvents($member, $access->eventIds);

            return $member;
        });
    }

    public function remove(ClientUser $member): void
    {
        if ($member->isOwner()) {
            throw new ConflictException('The owner can\'t be removed. Make someone else the owner first.');
        }

        $this->users->delete($member);
    }

    public function resendInvitation(ClientUser $member, ClientUser $by): void
    {
        if (! $member->isPending()) {
            throw new ConflictException("{$member->name} has already joined.");
        }

        $this->users->update($member, ['invited_at' => now()]);
        $this->sendInvitation($member, $member->client, $by);
    }

    public function transferOwnership(ClientUser $owner, ClientUser $member): void
    {
        if (! $owner->isOwner() || $member->client_id !== $owner->client_id || $member->is($owner)) {
            throw new ConflictException('Only the owner can hand the account to another user.');
        }

        if ($member->isPending()) {
            throw new ConflictException("{$member->name} has to accept the invitation first.");
        }

        $this->transaction(function () use ($owner, $member) {
            // The old owner steps down first: there can only be one owner.
            $this->users->update($owner, [
                'role' => ClientUserRole::Member,
                'permissions' => ClientPermission::values(),
                'all_events' => true,
            ]);
            $this->users->update($member, ['role' => ClientUserRole::Owner, 'all_events' => true]);
            $this->users->syncEvents($member, []);
        });
    }

    public function acceptInvitation(string $email, string $token, string $password): ?ClientUser
    {
        $accepted = null;

        $status = $this->invitations()->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (ClientUser $user, string $password) use (&$accepted) {
                $accepted = $this->users->update($user, [
                    'password' => $password,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'joined_at' => $user->joined_at ?? now(),
                ]);
                $accepted->forceFill(['remember_token' => Str::random(60)])->save();
            },
        );

        return $status === Password::PASSWORD_RESET ? $accepted : null;
    }

    public function grantEvent(ClientUser $user, Event $event): void
    {
        if (! $user->seesAllEvents()) {
            $this->users->attachEvent($user, $event->id);
        }
    }

    private function sendInvitation(ClientUser $member, Client $client, ClientUser $by): void
    {
        $member->notify(new ClientInvitation(
            token: $this->invitations()->createToken($member),
            accountName: $client->name,
            invitedBy: $by->name,
        ));
    }

    private function invitations(): PasswordBroker
    {
        /** @var PasswordBroker */
        return Password::broker('client_invitations');
    }
}
