<?php

namespace App\Domains\Client\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientTeamServiceInterface;
use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Http\Requests\Dashboard\InviteMemberRequest;
use App\Domains\Client\Http\Requests\Dashboard\UpdateMemberRequest;
use App\Domains\Client\Http\Resources\ClientUserResource;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The people who sign in to the account: invite them, choose what they can
 * do and which events they see, remove them, or hand over the account.
 * How many there can be comes from the shared `auth.client.plan`.
 */
class TeamController extends InertiaController
{
    use ResolvesClient;

    public function __construct(
        private readonly ClientTeamServiceInterface $team,
    ) {}

    public function index(Request $request, EventQueryServiceInterface $events): Response
    {
        $this->authorize('viewAny', ClientUser::class);
        $client = $this->client($request);

        return Inertia::render('client/team/index', [
            'members' => ClientUserResource::collection($this->team->members($client)),
            'events' => $events->listForClient($client)->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'event_date' => $event->event_date?->toDateString(),
            ]),
            'permissions' => ClientPermission::options(),
        ]);
    }

    public function store(InviteMemberRequest $request): RedirectResponse
    {
        $user = $this->clientUser($request);
        $member = $this->team->invite($user->client, $request->toData(), $user);

        return $this->backWithSuccess("Invitation sent to {$member->email}.");
    }

    public function update(UpdateMemberRequest $request, ClientUser $member): RedirectResponse
    {
        $this->team->updateAccess($member, $request->access());

        return $this->backWithSuccess("{$member->name}'s access updated.");
    }

    public function destroy(ClientUser $member): RedirectResponse
    {
        $this->authorize('delete', $member);

        $this->team->remove($member);

        return $this->backWithSuccess("{$member->name} can no longer sign in to this account.");
    }

    public function resend(Request $request, ClientUser $member): RedirectResponse
    {
        $this->authorize('update', $member);

        $this->team->resendInvitation($member, $this->clientUser($request));

        return $this->backWithSuccess("Invitation sent to {$member->email} again.");
    }

    public function transferOwnership(Request $request, ClientUser $member): RedirectResponse
    {
        $this->authorize('transferOwnership', $member);

        $this->team->transferOwnership($this->clientUser($request), $member);

        return $this->backWithSuccess("{$member->name} is now the owner of this account.");
    }
}
