<?php

namespace App\Domains\Rsvp\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
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
    ) {}

    public function index(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);

        $filters = RsvpFilters::fromArray($request->query());

        return Inertia::render('client/rsvps/index', [
            'event' => EventResource::make($event),
            'rsvps' => RsvpResource::collection($this->rsvpQueries->forEvent($event, $filters)),
            'summary' => $this->rsvpQueries->summary($event),
            'filters' => $filters->toArray(),
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
