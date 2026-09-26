<?php

namespace App\Domains\Event\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventLink;
use App\Domains\Guest\Models\Guest;
use Illuminate\Http\Request;

/**
 * Short links (domain/{slug}/{code}): the event's public URL and each guest's
 * RSVP link.
 */
interface EventLinkServiceInterface
{
    /** The event's public URL link; created once, then returned. */
    public function createForEvent(Event $event): EventLink;

    /** The guest's personal link; created once, then returned. */
    public function createForGuest(Guest $guest): EventLink;

    /** The link with this code, for an event that is not deleted; 404 otherwise. */
    public function resolve(string $code): EventLink;

    /** Counts an open by a person (link-preview crawlers are ignored). */
    public function recordOpen(EventLink $link, Request $request): void;
}
