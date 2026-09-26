<?php

namespace App\Domains\Rsvp\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Rsvp\Contracts\RsvpServiceInterface;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Http\RedirectResponse;

class RsvpDeliveryController extends InertiaController
{
    public function __construct(
        private readonly RsvpServiceInterface $rsvps,
    ) {}

    public function send(Rsvp $rsvp): RedirectResponse
    {
        $this->authorize('update', $rsvp);
        $this->rsvps->send($rsvp);

        return $this->backWithSuccess('RSVP link sent.');
    }

    public function resend(Rsvp $rsvp): RedirectResponse
    {
        $this->authorize('update', $rsvp);
        $this->rsvps->resend($rsvp);

        return $this->backWithSuccess('RSVP link re-sent.');
    }

    public function remind(Rsvp $rsvp): RedirectResponse
    {
        $this->authorize('update', $rsvp);
        $this->rsvps->remind($rsvp);

        return $this->backWithSuccess('Reminder sent.');
    }

    public function expire(Rsvp $rsvp): RedirectResponse
    {
        $this->authorize('update', $rsvp);
        $this->rsvps->expire($rsvp);

        return $this->backWithSuccess('RSVP link expired.');
    }
}
