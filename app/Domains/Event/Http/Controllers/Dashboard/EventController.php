<?php

namespace App\Domains\Event\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientTeamServiceInterface;
use App\Domains\Client\Models\Client;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Enums\EventPeriod;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Enums\EventType;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Http\Requests\Dashboard\StoreEventRequest;
use App\Domains\Event\Http\Requests\Dashboard\UpdateEventRequest;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Contracts\GuestQueryServiceInterface;
use App\Domains\Guest\Http\Resources\GuestResource;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use App\Domains\Rsvp\Support\RsvpMessage;
use App\Domains\Seating\Contracts\SeatingQueryServiceInterface;
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
    use ResolvesClient;

    /** Guests shown in the overview's "Waiting on reply" list. */
    private const WAITING_PREVIEW = 5;

    public function __construct(
        private readonly EventServiceInterface $events,
        private readonly EventQueryServiceInterface $eventQueries,
        private readonly TemplateQueryServiceInterface $templates,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->clientUser($request);
        $filters = EventFilters::fromArray($request->query(), defaultPeriod: EventPeriod::Upcoming);

        return Inertia::render('client/events/index', [
            'events' => EventResource::collection($this->eventQueries->forClient($user->client, $filters, $user)),
            'filters' => $filters->toArray(),
            'states' => EventState::options(),
            'totals' => $this->eventQueries->totalsForClient($user->client, $user),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Event::class);
        $client = $this->client($request);

        return Inertia::render('client/events/create', [
            'templates' => TemplateResource::collection($this->templates->available($client)),
            'eventTypes' => EventType::options(),
            'registrationTypes' => RegistrationType::detailedOptions(),
            'defaultInvitationMessage' => RsvpMessage::DEFAULT,
            'defaultReminderMessage' => RsvpMessage::DEFAULT_REMINDER,
            'defaultInvitationSubject' => RsvpMessage::DEFAULT_SUBJECT,
            'defaultReminderSubject' => RsvpMessage::DEFAULT_REMINDER_SUBJECT,
            // Arriving from the Templates page with a design already chosen.
            'selectedTemplateId' => $this->selectableTemplateId($request->query('template'), $client),
        ]);
    }

    public function store(StoreEventRequest $request, ClientTeamServiceInterface $team): RedirectResponse
    {
        $user = $this->clientUser($request);
        $event = $this->events->create($user->client, $request->toData());
        // A member who only sees some events sees the ones they create.
        $team->grantEvent($user, $event);

        return $this->toRouteWithSuccess('client.events.show', 'Event created as draft.', $event);
    }

    public function show(
        Event $event,
        GuestQueryServiceInterface $guests,
        RsvpQueryServiceInterface $rsvps,
        NotificationQueryServiceInterface $notifications,
        SeatingQueryServiceInterface $seating,
    ): Response {
        $this->authorize('view', $event);
        $event->load(['templateVersion.template.latestVersion', 'publicLink']);

        return Inertia::render('client/events/show', [
            'event' => EventResource::make($event),
            'guestSummary' => $guests->summary($event),
            'rsvpSummary' => $rsvps->summary($event),
            // The first parties still to reply, for the "Waiting on reply" list.
            'waiting' => GuestResource::collection($guests->waitingForEvent($event, self::WAITING_PREVIEW)->items()),
            'messageIssues' => $notifications->messageIssuesForEvents([$event->id])[$event->id] ?? ['failed' => 0, 'queued' => 0],
            'seated' => $seating->confirmedSeatedCount($event),
        ]);
    }

    /** The Settings tab: the event's details, registration, publishing and deletion. */
    public function edit(Event $event): Response
    {
        $this->authorize('update', $event);
        $event->load(['publicLink', 'templateVersion.template.latestVersion']);

        return Inertia::render('client/events/edit', [
            'event' => EventResource::make($event),
            'eventTypes' => EventType::options(),
            'registrationTypes' => RegistrationType::detailedOptions(),
            'defaultInvitationMessage' => RsvpMessage::DEFAULT,
            'defaultReminderMessage' => RsvpMessage::DEFAULT_REMINDER,
            'defaultInvitationSubject' => RsvpMessage::DEFAULT_SUBJECT,
            'defaultReminderSubject' => RsvpMessage::DEFAULT_REMINDER_SUBJECT,
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->events->update($event, $request->toData());

        return $this->toRouteWithSuccess('client.events.edit', 'Event updated.', $event);
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
}
