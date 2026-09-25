<?php

namespace App\Domains\Event\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\Models\Event;
use Illuminate\Http\RedirectResponse;

class EventRegistrationController extends InertiaController
{
    public function __construct(
        private readonly EventServiceInterface $events,
    ) {}

    public function open(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $this->events->openRegistration($event);

        return $this->backWithSuccess('Registration opened.');
    }

    public function close(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $this->events->closeRegistration($event);

        return $this->backWithSuccess('Registration closed.');
    }
}
