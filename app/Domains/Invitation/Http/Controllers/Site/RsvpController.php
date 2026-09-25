<?php

namespace App\Domains\Invitation\Http\Controllers\Site;

use App\Core\Http\Controllers\InertiaController;
use App\Core\Http\Responses\InvitationPage;
use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Invitation\Contracts\InvitationQueryServiceInterface;
use App\Domains\Invitation\Contracts\InvitationServiceInterface;
use App\Domains\Invitation\Http\Resources\RsvpResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The guest's invitation page (/rsvp/{token}, the link sent to the guest).
 * An expired invitation on accept/decline surfaces as a flash error through
 * the DomainException handler in bootstrap/app.php.
 */
class RsvpController extends InertiaController
{
    public function __construct(
        private readonly InvitationServiceInterface $invitations,
        private readonly InvitationQueryServiceInterface $invitationQueries,
        private readonly EventDesignServiceInterface $designs,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $invitation = $this->invitationQueries->findByToken($token);

        // Round-trip through JSON so the nested event/guest resources resolve too.
        /** @var array<string, mixed> $data */
        $data = json_decode(RsvpResource::withDesign(
            $invitation,
            $this->designs->forGuest($invitation->event, $invitation->guest->name),
        )->toJson(), true);

        return InvitationPage::render($data['design'], $data['event'], [
            'mode' => 'rsvp',
            'invitation' => [
                'status' => $data['status'],
                'is_expired' => $data['is_expired'],
                'expires_at' => $data['expires_at'],
                'responded_at' => $data['responded_at'],
            ],
            'guest' => $data['guest'],
            'actions' => [
                'accept' => route('site.rsvp.accept', $token),
                'decline' => route('site.rsvp.decline', $token),
            ],
        ]);
    }

    public function accept(string $token): RedirectResponse
    {
        $this->invitations->accept($token);

        return $this->backWithSuccess('Thank you! Your RSVP is confirmed.');
    }

    public function decline(string $token): RedirectResponse
    {
        $this->invitations->decline($token);

        return $this->backWithSuccess('Thanks for letting us know.');
    }
}
