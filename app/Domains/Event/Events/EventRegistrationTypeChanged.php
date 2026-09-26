<?php

namespace App\Domains\Event\Events;

use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised inside the update's transaction when an event's registration type
 * changes. The Guest domain approves everyone on a guest-list-only event.
 */
final class EventRegistrationTypeChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Event $event,
        public readonly RegistrationType $previous,
    ) {}
}
