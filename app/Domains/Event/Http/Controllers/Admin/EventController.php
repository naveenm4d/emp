<?php

namespace App\Domains\Event\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Http\Resources\ClientResource;
use App\Domains\Client\Models\Client;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Enums\EventType;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Http\Requests\Admin\StoreEventRequest;
use App\Domains\Event\Http\Requests\Admin\UpdateEventRequest;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use App\Domains\Rsvp\Support\RsvpMessage;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Http\Resources\TemplateResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff manage events on behalf of a client. Routes are nested under
 * /admin/clients/{client} with scoped bindings, so {event} always belongs to {client}.
 */
class EventController extends InertiaController
{
    public function __construct(
        private readonly EventQueryServiceInterface $events,
        private readonly EventServiceInterface $eventService,
        private readonly TemplateQueryServiceInterface $templates,
    ) {}

    public function index(Request $request): Response
    {
        $filters = EventFilters::fromArray($request->query());

        return Inertia::render('admin/events/index', [
            'events' => EventResource::collection($this->events->all($filters)),
            'filters' => $filters->toArray(),
            'states' => EventState::options(),
        ]);
    }

    public function create(Client $client): Response
    {
        return Inertia::render('admin/events/create', [
            'client' => ClientResource::make($client),
            'templates' => TemplateResource::collection($this->templates->available($client)),
            'eventTypes' => EventType::options(),
            'registrationTypes' => RegistrationType::detailedOptions(),
            'defaultInvitationMessage' => RsvpMessage::DEFAULT,
            'defaultReminderMessage' => RsvpMessage::DEFAULT_REMINDER,
        ]);
    }

    public function store(StoreEventRequest $request, Client $client): RedirectResponse
    {
        $event = $this->eventService->create($client, $request->toData());

        return $this->toRouteWithSuccess('admin.clients.events.edit', 'Event created as draft.', [$client, $event]);
    }

    public function edit(Client $client, Event $event): Response
    {
        $event->load(['templateVersion.template.latestVersion', 'publicLink']);

        return Inertia::render('admin/events/edit', [
            'client' => ClientResource::make($client),
            'event' => EventResource::make($event),
            'templates' => TemplateResource::collection($this->templates->available($client)),
            'eventTypes' => EventType::options(),
            'registrationTypes' => RegistrationType::detailedOptions(),
            'defaultInvitationMessage' => RsvpMessage::DEFAULT,
            'defaultReminderMessage' => RsvpMessage::DEFAULT_REMINDER,
        ]);
    }

    public function update(UpdateEventRequest $request, Client $client, Event $event): RedirectResponse
    {
        $this->eventService->update($event, $request->toData());

        return $this->backWithSuccess('Event updated.');
    }

    public function destroy(Client $client, Event $event): RedirectResponse
    {
        $this->eventService->delete($event);

        return $this->toRouteWithSuccess('admin.clients.show', 'Event deleted.', $client);
    }
}
