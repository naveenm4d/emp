<?php

namespace App\Domains\Rsvp\Http\Responses;

use App\Core\Http\Responses\InvitationPage;
use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Models\EventLink;
use App\Domains\Rsvp\Http\Resources\PublicRsvpResource;
use App\Domains\Rsvp\Models\Rsvp;
use Inertia\Response;

/**
 * The guest's RSVP page behind their personal link (domain/{slug}/{code}):
 * the invitation rendered for them plus the accept/decline buttons.
 */
final class RsvpPage
{
    public static function render(Rsvp $rsvp, EventLink $link): Response
    {
        // Round-trip through JSON so the nested event/guest resources resolve too.
        /** @var array<string, mixed> $data */
        $data = json_decode(PublicRsvpResource::withDesign(
            $rsvp,
            app(EventDesignServiceInterface::class)->forGuest($rsvp->event, $rsvp->guest->name),
        )->toJson(), true);

        $route = ['slug' => $rsvp->event->slug, 'code' => $link->code];

        return InvitationPage::render($data['design'], $data['event'], [
            'mode' => 'rsvp',
            'rsvp' => [
                'status' => $data['status'],
                'is_expired' => $data['is_expired'],
                'expires_at' => $data['expires_at'],
                'responded_at' => $data['responded_at'],
            ],
            'guest' => $data['guest'],
            'actions' => [
                'accept' => route('web.rsvp.accept', $route),
                'decline' => route('web.rsvp.decline', $route),
            ],
        ]);
    }
}
