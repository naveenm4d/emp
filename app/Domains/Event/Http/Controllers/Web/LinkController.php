<?php

namespace App\Domains\Event\Http\Controllers\Web;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\Controller;
use App\Domains\Event\Contracts\EventLinkServiceInterface;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Http\Responses\EventPage;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use App\Domains\Rsvp\Http\Responses\RsvpPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Every public link is domain/{slug}/{code}. The code identifies the link;
 * the slug is a label, and an outdated one redirects to the current slug.
 * A guest's link opens their RSVP page; the event's link opens the public page.
 */
class LinkController extends Controller
{
    public function __construct(
        private readonly EventLinkServiceInterface $links,
        private readonly RsvpQueryServiceInterface $rsvps,
    ) {}

    public function __invoke(Request $request, string $slug, string $code): Response|RedirectResponse
    {
        $link = $this->links->resolve($code);

        if ($slug !== $link->event->slug) {
            return redirect()->to($link->url(), 301);
        }

        $rsvp = $link->guest ? $this->rsvps->findLatestForGuest($link->guest) : null;

        // Only a guest's own link reaches an unpublished event (their RSVP was sent to them).
        if ($rsvp === null && $link->event->state !== EventState::Published) {
            throw new NotFoundException('Event not found.');
        }

        $this->links->recordOpen($link, $request);

        return $rsvp ? RsvpPage::render($rsvp, $link) : EventPage::render($link->event, $request);
    }
}
