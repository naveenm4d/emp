<?php

namespace App\Domains\Guest\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Contracts\GuestQueryServiceInterface;
use App\Domains\Guest\Contracts\GuestServiceInterface;
use App\Domains\Guest\DTOs\GuestFilters;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Enums\GuestSource;
use App\Domains\Guest\Http\Requests\Dashboard\StoreGuestRequest;
use App\Domains\Guest\Http\Requests\Dashboard\UpdateGuestRequest;
use App\Domains\Guest\Http\Resources\GuestDetails;
use App\Domains\Guest\Http\Resources\GuestResource;
use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Support\RsvpMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuestController extends InertiaController
{
    public function __construct(
        private readonly GuestServiceInterface $guests,
        private readonly GuestQueryServiceInterface $guestQueries,
    ) {}

    public function index(Request $request, Event $event, GuestDetails $details): Response
    {
        $this->authorize('view', $event);

        $filters = GuestFilters::fromArray($request->query());

        return Inertia::render('client/guests/index', [
            'event' => EventResource::make($event),
            'guests' => GuestResource::collection($this->guestQueries->forEvent($event, $filters)),
            'summary' => $this->guestQueries->summary($event),
            'filters' => $filters->toArray(),
            // The guest open in the details panel (?guest=).
            'guestDetails' => fn () => $details->forRequest($event, $request),
            'defaultInvitationMessage' => RsvpMessage::DEFAULT,
            'defaultReminderMessage' => RsvpMessage::DEFAULT_REMINDER,
            'options' => [
                'sources' => GuestSource::options(),
                'approval_statuses' => ApprovalStatus::options(),
                'rsvp_statuses' => GuestRsvpStatus::options(),
            ],
        ]);
    }

    public function store(StoreGuestRequest $request, Event $event): RedirectResponse
    {
        $this->guests->add($event, $request->toData());

        return $this->backWithSuccess('Guest added.');
    }

    public function update(UpdateGuestRequest $request, Guest $guest): RedirectResponse
    {
        $this->guests->update($guest, $request->toData());

        return $this->backWithSuccess('Guest updated.');
    }

    public function destroy(Guest $guest): RedirectResponse
    {
        $this->authorize('delete', $guest);

        $this->guests->delete($guest);

        return $this->backWithSuccess('Guest removed.');
    }
}
