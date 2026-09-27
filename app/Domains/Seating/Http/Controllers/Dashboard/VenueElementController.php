<?php

namespace App\Domains\Seating\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Models\Event;
use App\Domains\Seating\Contracts\SeatingServiceInterface;
use App\Domains\Seating\Http\Requests\Dashboard\StoreVenueElementRequest;
use App\Domains\Seating\Http\Requests\Dashboard\UpdateVenueElementRequest;
use App\Domains\Seating\Models\VenueElement;
use Illuminate\Http\RedirectResponse;

/**
 * Venue elements on the seating floor plan: the stage, poruwa, buffet, …
 * (moving them happens with the tables, in SeatingController::layout).
 */
class VenueElementController extends InertiaController
{
    public function __construct(
        private readonly SeatingServiceInterface $seating,
    ) {}

    public function store(StoreVenueElementRequest $request, Event $event): RedirectResponse
    {
        $element = $this->seating->createVenueElement($event, $request->toData());

        return $this->backWithSuccess("{$element->displayLabel()} added.");
    }

    public function update(UpdateVenueElementRequest $request, VenueElement $venueElement): RedirectResponse
    {
        $element = $this->seating->updateVenueElement($venueElement, $request->toData());

        return $this->backWithSuccess("{$element->displayLabel()} saved.");
    }

    public function destroy(VenueElement $venueElement): RedirectResponse
    {
        $this->authorize('delete', $venueElement);

        $this->seating->deleteVenueElement($venueElement);

        return $this->backWithSuccess("{$venueElement->displayLabel()} removed.");
    }
}
