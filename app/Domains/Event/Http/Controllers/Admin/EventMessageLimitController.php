<?php

namespace App\Domains\Event\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Models\Client;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\Http\Requests\Admin\UpdateMessageLimitsRequest;
use App\Domains\Event\Models\Event;
use Illuminate\Http\RedirectResponse;

/**
 * Staff cap how many WhatsApp invitations and reminders each guest of an
 * event can get. Clients see the limits but can't change them.
 */
class EventMessageLimitController extends InertiaController
{
    public function __construct(
        private readonly EventServiceInterface $events,
    ) {}

    public function update(UpdateMessageLimitsRequest $request, Client $client, Event $event): RedirectResponse
    {
        $this->events->setMessageLimits($event, $request->invitations(), $request->reminders());

        return $this->backWithSuccess('Message limits saved.');
    }
}
