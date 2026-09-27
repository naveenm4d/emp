<?php

namespace App\Domains\Rsvp\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\Enums\PlanFeature;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Http\Resources\GuestDetails;
use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use App\Domains\Rsvp\Contracts\RsvpServiceInterface;
use App\Domains\Rsvp\DTOs\RsvpFilters;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Http\Resources\RsvpResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RsvpController extends InertiaController
{
    public function __construct(
        private readonly RsvpServiceInterface $rsvps,
        private readonly RsvpQueryServiceInterface $rsvpQueries,
        private readonly ClientPlanServiceInterface $plans,
    ) {}

    public function index(Request $request, Event $event, GuestDetails $details): Response
    {
        $this->authorize('view', $event);

        // Not in the plan: the tab shows an upgrade prompt, so nothing else is loaded.
        if (! $this->plans->hasFeature($event->client, PlanFeature::RsvpList)) {
            return Inertia::render('client/rsvps/index', ['event' => EventResource::make($event), 'locked' => true]);
        }

        $filters = RsvpFilters::fromArray($request->query());

        return Inertia::render('client/rsvps/index', [
            'event' => EventResource::make($event),
            'rsvps' => RsvpResource::collection($this->rsvpQueries->forEvent($event, $filters)),
            'summary' => $this->rsvpQueries->summary($event),
            'filters' => $filters->toArray(),
            // The guest open in the details panel (?guest=).
            'guestDetails' => fn () => $details->forRequest($event, $request),
            'statuses' => RsvpStatus::options(),
        ]);
    }

    public function store(Request $request, Guest $guest): RedirectResponse
    {
        $this->authorize('update', $guest);

        $rsvp = $this->rsvps->create($guest);

        if ($request->boolean('send')) {
            $this->rsvps->send($rsvp);

            return $this->backWithSuccess("RSVP link sent to {$guest->name}.");
        }

        return $this->backWithSuccess("RSVP link created for {$guest->name}.");
    }
}
