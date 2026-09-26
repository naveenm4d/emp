<?php

namespace App\Domains\Event\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Models\Client;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\Models\Event;
use Illuminate\Http\RedirectResponse;

class EventRegistrationController extends InertiaController
{
    public function __construct(
        private readonly EventServiceInterface $events,
    ) {}

    public function open(Client $client, Event $event): RedirectResponse
    {
        $this->events->openRegistration($event);

        return $this->backWithSuccess('Registration opened.');
    }

    public function close(Client $client, Event $event): RedirectResponse
    {
        $this->events->closeRegistration($event);

        return $this->backWithSuccess('Registration closed.');
    }
}
