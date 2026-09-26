<?php

namespace App\Domains\Rsvp\Http\Controllers\Web;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Rsvp\Contracts\RsvpServiceInterface;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Http\Requests\Web\RespondRsvpRequest;
use Illuminate\Http\RedirectResponse;

/**
 * The guest's answer from their RSVP page (domain/{slug}/{code}, rendered by
 * LinkController). It answers the guest's latest RSVP link. An expired link
 * surfaces as a flash error through the DomainException handler.
 */
class RsvpController extends InertiaController
{
    public function __construct(
        private readonly RsvpServiceInterface $rsvps,
    ) {}

    public function respond(RespondRsvpRequest $request, string $slug, string $code): RedirectResponse
    {
        $rsvp = $this->rsvps->respond($request->rsvp()->token, $request->attendance(), $request->details(), $request->note());

        return $this->backWithSuccess(match ($rsvp->status) {
            RsvpStatus::Accepted => 'Thank you! Your RSVP is confirmed.',
            RsvpStatus::Maybe => "Thanks! We've noted that you might come.",
            default => 'Thanks for letting us know.',
        });
    }
}
