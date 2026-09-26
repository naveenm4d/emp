<?php

namespace App\Domains\Rsvp\Http\Controllers\Web;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Contracts\EventLinkServiceInterface;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use App\Domains\Rsvp\Contracts\RsvpServiceInterface;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Http\RedirectResponse;

/**
 * Accept / decline from the guest's RSVP page (domain/{slug}/{code}, rendered
 * by LinkController). They answer the guest's latest RSVP link. An expired
 * link surfaces as a flash error through the DomainException handler.
 */
class RsvpController extends InertiaController
{
    public function __construct(
        private readonly RsvpServiceInterface $rsvps,
        private readonly RsvpQueryServiceInterface $rsvpQueries,
        private readonly EventLinkServiceInterface $links,
    ) {}

    public function accept(string $slug, string $code): RedirectResponse
    {
        $this->rsvps->accept($this->rsvpFor($code)->token);

        return $this->backWithSuccess('Thank you! Your RSVP is confirmed.');
    }

    public function decline(string $slug, string $code): RedirectResponse
    {
        $this->rsvps->decline($this->rsvpFor($code)->token);

        return $this->backWithSuccess('Thanks for letting us know.');
    }

    private function rsvpFor(string $code): Rsvp
    {
        $link = $this->links->resolve($code);

        return ($link->guest ? $this->rsvpQueries->findLatestForGuest($link->guest) : null)
            ?? throw new NotFoundException('RSVP link not found.');
    }
}
