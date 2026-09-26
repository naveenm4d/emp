<?php

namespace App\Domains\Event\Http\Controllers\Web;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\Controller;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Models\EventLink;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use Illuminate\Http\RedirectResponse;

/** Links in older formats (/e/{slug}, /e/{slug}/{guest}, /rsvp/{token}) redirect to the short links. */
class LegacyLinkController extends Controller
{
    public function event(EventQueryServiceInterface $events, string $slug): RedirectResponse
    {
        $event = $events->findLatestBySlug($slug)?->loadMissing('publicLink');

        return $this->redirectTo($event?->publicLink);
    }

    public function guest(string $slug, string $guest): RedirectResponse
    {
        return $this->redirectTo(EventLink::query()->where('guest_id', $guest)->whereHas('event')->first());
    }

    public function rsvp(RsvpQueryServiceInterface $rsvps, string $token): RedirectResponse
    {
        return redirect()->to($rsvps->findByToken($token)->rsvpUrl(), 301);
    }

    private function redirectTo(?EventLink $link): RedirectResponse
    {
        return $link
            ? redirect()->to($link->url(), 301)
            : throw new NotFoundException('Link not found.');
    }
}
