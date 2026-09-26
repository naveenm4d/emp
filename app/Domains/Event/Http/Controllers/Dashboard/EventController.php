<?php

namespace App\Domains\Event\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Models\Client;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Enums\EventType;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Http\Requests\Dashboard\StoreEventRequest;
use App\Domains\Event\Http\Requests\Dashboard\UpdateEventRequest;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Contracts\GuestQueryServiceInterface;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use App\Domains\Rsvp\Support\RsvpMessage;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Exceptions\TemplateNotAvailableException;
use App\Domains\Template\Http\Resources\TemplateResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends InertiaController
{
    public function __construct(
        private readonly EventServiceInterface $events,
        private readonly EventQueryServiceInterface $eventQueries,
        private readonly TemplateQueryServiceInterface $templates,
    ) {}

    public function index(Request $request): Response
    {
        $filters = EventFilters::fromArray($request->query());

        return Inertia::render('client/events/index', [
            'events' => EventResource::collection($this->eventQueries->forClient($this->client($request), $filters)),
            'filters' => $filters->toArray(),
            'states' => EventState::options(),
        ]);
    }

    public function create(Request $request): Response
    {
        $client = $this->client($request);

        return Inertia::render('client/events/create', [
            'templates' => TemplateResource::collection($this->templates->available($client)),
            'eventTypes' => EventType::options(),
            'registrationTypes' => RegistrationType::detailedOptions(),
            'defaultInvitationMessage' => RsvpMessage::DEFAULT,
            'defaultReminderMessage' => RsvpMessage::DEFAULT_REMINDER,
            // Arriving from the Templates page with a design already chosen.
            'selectedTemplateId' => $this->selectableTemplateId($request->query('template'), $client),
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $event = $this->events->create($this->client($request), $request->toData());

        return $this->toRouteWithSuccess('client.events.show', 'Event created as draft.', $event);
    }

    public function show(
        Event $event,
        GuestQueryServiceInterface $guests,
        RsvpQueryServiceInterface $rsvps,
    ): Response {
        $this->authorize('view', $event);
        $event->load(['templateVersion.template.latestVersion', 'publicLink']);

        return Inertia::render('client/events/show', [
            'event' => EventResource::make($event),
            'guestSummary' => $guests->summary($event),
            'rsvpSummary' => $rsvps->summary($event),
            'eventTypes' => EventType::options(),
            'registrationTypes' => RegistrationType::detailedOptions(),
            'defaultInvitationMessage' => RsvpMessage::DEFAULT,
            'defaultReminderMessage' => RsvpMessage::DEFAULT_REMINDER,
        ]);
    }

    /** Details are edited on the overview now; the design on the Design tab. */
    public function edit(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        return to_route('client.events.show', $event);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->events->update($event, $request->toData());

        return $this->toRouteWithSuccess('client.events.show', 'Event updated.', $event);
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $this->events->delete($event);

        return $this->toRouteWithSuccess('client.events.index', 'Event deleted.');
    }

    private function selectableTemplateId(mixed $templateId, Client $client): ?string
    {
        if (! is_string($templateId) || ! Str::isUuid($templateId)) {
            return null;
        }

        try {
            return $this->templates->findSelectable($templateId, $client)->id;
        } catch (TemplateNotAvailableException) {
            return null;
        }
    }

    private function client(Request $request): Client
    {
        /** @var Client */
        return $request->user('client');
    }
}
