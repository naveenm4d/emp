<?php

namespace App\Domains\Event\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Models\Client;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\Http\Requests\Admin\UpdateExtraGuestsRequest;
use App\Domains\Event\Models\Event;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Http\RedirectResponse;

/**
 * Staff raise an event's guest limit beyond its plan (Celebration, Business)
 * after payment, in blocks of extra guests. Logged with a note.
 */
class EventExtraGuestsController extends InertiaController
{
    public function __construct(
        private readonly EventServiceInterface $events,
    ) {}

    public function update(UpdateExtraGuestsRequest $request, Client $client, Event $event): RedirectResponse
    {
        /** @var StaffMember $staff */
        $staff = $request->user('staff');

        $event = $this->events->setExtraGuests($event, $request->blocks(), $staff, $request->note());

        return $this->backWithSuccess("{$event->title} now allows up to {$event->guestLimit()} guests.");
    }
}
