<?php

namespace App\Domains\Seating\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\Enums\PlanFeature;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Seating\Contracts\SeatingQueryServiceInterface;
use App\Domains\Seating\Contracts\SeatingServiceInterface;
use App\Domains\Seating\Enums\SeatMode;
use App\Domains\Seating\Enums\TableShape;
use App\Domains\Seating\Enums\VenueElementKind;
use App\Domains\Seating\Http\Requests\Dashboard\AssignSeatRequest;
use App\Domains\Seating\Http\Requests\Dashboard\SaveFloorPlanRequest;
use App\Domains\Seating\Http\Requests\Dashboard\SeatingPairRequest;
use App\Domains\Seating\Http\Requests\Dashboard\StoreTableRequest;
use App\Domains\Seating\Http\Requests\Dashboard\UpdateTableRequest;
use App\Domains\Seating\Http\Resources\EventTableResource;
use App\Domains\Seating\Http\Resources\SeatingGuestResource;
use App\Domains\Seating\Http\Resources\VenueElementResource;
use App\Domains\Seating\Models\EventTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The event's Seating tab: tables, their seats, and who sits where.
 */
class SeatingController extends InertiaController
{
    public function __construct(
        private readonly SeatingServiceInterface $seating,
        private readonly SeatingQueryServiceInterface $queries,
        private readonly ClientPlanServiceInterface $plans,
    ) {}

    public function index(Event $event): Response
    {
        $this->authorize('view', $event);
        // The event header's poster shows the invitation's image.
        $event->loadMissing('templateVersion.template.latestVersion');

        // Not in the plan: the tab shows an upgrade prompt, so nothing else is loaded.
        if (! $this->plans->hasFeature($event->client, PlanFeature::Seating)) {
            return Inertia::render('client/events/seating', ['event' => EventResource::make($event), 'locked' => true]);
        }

        $event->load(['templateVersion.template.latestVersion', 'publicLink']);

        $tables = $this->queries->tables($event);
        $guests = $this->queries->guests($event);

        return Inertia::render('client/events/seating', [
            'event' => EventResource::make($event),
            'tables' => EventTableResource::collection($tables)->resolve(),
            'guests' => SeatingGuestResource::collection($guests)->resolve(),
            'summary' => $this->queries->summary($tables, $guests),
            'tableShapes' => TableShape::options(),
            'venueElements' => VenueElementResource::collection($this->queries->venueElements($event))->resolve(),
            'venueElementKinds' => VenueElementKind::options(),
        ]);
    }

    /** Saves where tables and venue elements stand on the floor plan (after a drag; no flash). */
    public function layout(SaveFloorPlanRequest $request, Event $event): RedirectResponse
    {
        $this->seating->saveFloorPlan($event, $request->toData());

        return back();
    }

    public function storeTable(StoreTableRequest $request, Event $event): RedirectResponse
    {
        $table = $this->seating->createTable($event, $request->toData());

        return $this->backWithSuccess("{$table->name} added.");
    }

    public function updateTable(UpdateTableRequest $request, EventTable $table): RedirectResponse
    {
        $table = $this->seating->updateTable($table, $request->toData());

        return $this->backWithSuccess("{$table->name} saved.");
    }

    public function destroyTable(EventTable $table): RedirectResponse
    {
        $this->authorize('delete', $table);

        $this->seating->deleteTable($table);

        return $this->backWithSuccess("{$table->name} removed; its guests need new seats.");
    }

    public function assign(AssignSeatRequest $request, EventTable $table): RedirectResponse
    {
        $guest = $request->guest();

        if ($request->mode() === SeatMode::Member) {
            $this->seating->assignMember($guest, $request->partyMember(), $table, $request->seatNumber());

            return $this->backWithSuccess("{$guest->partyMemberLabel($request->partyMember())} seated at {$table->name}.");
        }

        $seated = $this->seating->assign($guest, $table, $request->seatNumber(), $request->mode());
        $waiting = $guest->partySize() - $guest->seats()->count();

        return $this->backWithSuccess(match (true) {
            $request->mode() === SeatMode::Party => "{$guest->name} seated at {$table->name}.",
            $waiting === 0 => "{$guest->name}'s party is seated.",
            default => "{$seated} of {$guest->name}'s party seated at {$table->name}; {$waiting} still ".($waiting === 1 ? 'needs a seat' : 'need seats').'.',
        });
    }

    /** Frees the party's seats, or one person's (?party_member=). */
    public function unassign(Request $request, Guest $guest): RedirectResponse
    {
        $this->authorize('update', $guest);

        if ($request->filled('party_member')) {
            $member = (int) $request->query('party_member');
            $this->seating->unassignMember($guest, $member);

            return $this->backWithSuccess("{$guest->partyMemberLabel($member)}'s seat is free.");
        }

        $this->seating->unassign($guest);

        return $this->backWithSuccess("{$guest->name}'s seats are free.");
    }

    public function swap(SeatingPairRequest $request, Event $event): RedirectResponse
    {
        $this->seating->swap($request->guest(), $request->otherGuest());

        return $this->backWithSuccess('Seats swapped.');
    }

    public function replace(SeatingPairRequest $request, Event $event): RedirectResponse
    {
        $other = $request->otherGuest();
        $this->seating->replace($request->guest(), $other);

        return $this->backWithSuccess("{$other->name} now sits there.");
    }

    public function autoSeat(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        ['seated' => $seated, 'unplaced' => $unplaced] = $this->seating->autoSeat($event);

        return $this->backWithSuccess(match (true) {
            $seated === 0 && $unplaced === 0 => 'Every confirmed guest already has a seat.',
            $unplaced === 0 => "Seated {$seated} ".str('guest')->plural($seated).'.',
            default => "Seated {$seated} ".str('guest')->plural($seated).". {$unplaced} didn't fit: add a table or seats.",
        });
    }
}
