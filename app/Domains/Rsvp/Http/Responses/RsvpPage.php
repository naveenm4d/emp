<?php

namespace App\Domains\Rsvp\Http\Responses;

use App\Core\Http\Responses\InvitationPage;
use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Http\Resources\PublicRegistrationFormResource;
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
        $guest = $rsvp->guest->load('answers');
        $rsvp->event->loadMissing('registrationQuestions');

        return InvitationPage::render($data['design'], $data['event'], [
            'mode' => 'rsvp',
            'rsvp' => [
                'status' => $data['status'],
                'is_expired' => $data['is_expired'],
                'expires_at' => $data['expires_at'],
                'responded_at' => $data['responded_at'],
                'can_change' => $rsvp->canChangeResponse(),
            ],
            'guest' => $data['guest'],
            'form' => PublicRegistrationFormResource::forRsvp($rsvp->event, $guest)->resolve(),
            // What the guest already told us, to fill the form in again.
            'response' => [
                'email' => $guest->email,
                'phone' => $guest->phone,
                'address' => $guest->address,
                'company' => $guest->company,
                'job_title' => $guest->job_title,
                'additional_guests' => $guest->additional_guests,
                'children' => $guest->children,
                'dietary_restrictions' => $guest->dietary_restrictions ?? [],
                'dietary_notes' => $guest->dietary_notes,
                'answers' => $guest->answers->pluck('value', 'question_id')->all(),
            ],
            'actions' => [
                'respond' => route('web.rsvp.respond', $route),
            ],
        ]);
    }
}
