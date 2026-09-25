<?php

namespace App\Domains\Invitation\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Invitation\Contracts\InvitationQueryServiceInterface;
use App\Domains\Invitation\Contracts\InvitationServiceInterface;
use App\Domains\Invitation\DTOs\InvitationFilters;
use App\Domains\Invitation\Enums\InvitationStatus;
use App\Domains\Invitation\Http\Resources\InvitationResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends InertiaController
{
    public function __construct(
        private readonly InvitationServiceInterface $invitations,
        private readonly InvitationQueryServiceInterface $invitationQueries,
    ) {}

    public function index(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);

        $filters = InvitationFilters::fromArray($request->query());

        return Inertia::render('client/invitations/index', [
            'event' => EventResource::make($event),
            'invitations' => InvitationResource::collection($this->invitationQueries->forEvent($event, $filters)),
            'summary' => $this->invitationQueries->summary($event),
            'filters' => $filters->toArray(),
            'statuses' => InvitationStatus::options(),
        ]);
    }

    public function store(Request $request, Guest $guest): RedirectResponse
    {
        $this->authorize('update', $guest);

        $invitation = $this->invitations->create($guest);

        if ($request->boolean('send')) {
            $this->invitations->send($invitation);

            return $this->backWithSuccess("Invitation sent to {$guest->name}.");
        }

        return $this->backWithSuccess("Invitation created for {$guest->name}.");
    }
}
