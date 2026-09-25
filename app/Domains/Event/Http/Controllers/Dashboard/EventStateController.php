<?php

namespace App\Domains\Event\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\Http\Requests\Dashboard\TransitionEventRequest;
use App\Domains\Event\Models\Event;
use Illuminate\Http\RedirectResponse;

class EventStateController extends InertiaController
{
    public function __construct(
        private readonly EventServiceInterface $events,
    ) {}

    public function update(TransitionEventRequest $request, Event $event): RedirectResponse
    {
        $event = $this->events->transition($event, $request->state());

        return $this->backWithSuccess("Event is now {$event->state->value}.");
    }
}
