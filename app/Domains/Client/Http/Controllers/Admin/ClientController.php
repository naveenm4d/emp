<?php

namespace App\Domains\Client\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\Contracts\ClientQueryServiceInterface;
use App\Domains\Client\Contracts\ClientServiceInterface;
use App\Domains\Client\Contracts\ClientTeamServiceInterface;
use App\Domains\Client\DTOs\ClientFilters;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Http\Requests\Admin\UpdateClientRequest;
use App\Domains\Client\Http\Resources\ClientResource;
use App\Domains\Client\Http\Resources\ClientUserResource;
use App\Domains\Client\Models\Client;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Staff\Contracts\StaffActivityQueryServiceInterface;
use App\Domains\Staff\Http\Resources\StaffActivityResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends InertiaController
{
    public function __construct(
        private readonly ClientQueryServiceInterface $clients,
        private readonly ClientServiceInterface $clientService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = ClientFilters::fromArray($request->query());

        return Inertia::render('admin/clients/index', [
            'clients' => ClientResource::collection($this->clients->search($filters)),
            'filters' => $filters->toArray(),
        ]);
    }

    /** The client's account details and their events, managed by staff. */
    public function show(
        Request $request,
        Client $client,
        EventQueryServiceInterface $events,
        ClientPlanServiceInterface $plans,
        StaffActivityQueryServiceInterface $activities,
        ClientTeamServiceInterface $team,
    ): Response {
        $filters = EventFilters::fromArray($request->query());

        return Inertia::render('admin/clients/show', [
            'client' => ClientResource::make($client->load('owner')),
            'plan' => $plans->usage($client),
            'plans' => ClientPlan::options(),
            // Who signs in to the account.
            'team' => ClientUserResource::collection($team->members($client)),
            // Who changed the plan, when and why (only for staff who can read the activity log).
            'planHistory' => $request->user('staff')?->can('activity.read')
                ? StaffActivityResource::collection($activities->forClient($client->id, 'admin.clients.plan'))->resolve()
                : null,
            'events' => EventResource::collection($events->forClient($client, $filters)),
            'filters' => $filters->toArray(),
            'states' => EventState::options(),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->clientService->updateAccount($client, $request->toData());

        // The new password is the owner's: they sign in for the account.
        if (($password = $request->password()) !== null && $client->owner !== null) {
            $this->clientService->updatePassword($client->owner, $password);
        }

        return $this->backWithSuccess('Client updated.');
    }
}
