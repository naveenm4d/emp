<?php

namespace App\Domains\Event\Events;

use App\Domains\Event\Models\Event;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when anything that appears in the generated invitation changes:
 * the event's details, its template (version) or its uploaded media.
 * Dispatched after commit so the renderer sees the saved state.
 */
final class EventDesignChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Event $event,
    ) {}
}
