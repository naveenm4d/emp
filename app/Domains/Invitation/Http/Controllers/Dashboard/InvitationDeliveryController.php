<?php

namespace App\Domains\Invitation\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Invitation\Contracts\InvitationServiceInterface;
use App\Domains\Invitation\Models\Invitation;
use Illuminate\Http\RedirectResponse;

class InvitationDeliveryController extends InertiaController
{
    public function __construct(
        private readonly InvitationServiceInterface $invitations,
    ) {}

    public function send(Invitation $invitation): RedirectResponse
    {
        $this->authorize('update', $invitation);
        $this->invitations->send($invitation);

        return $this->backWithSuccess('Invitation sent.');
    }

    public function resend(Invitation $invitation): RedirectResponse
    {
        $this->authorize('update', $invitation);
        $this->invitations->resend($invitation);

        return $this->backWithSuccess('Invitation re-sent.');
    }

    public function expire(Invitation $invitation): RedirectResponse
    {
        $this->authorize('update', $invitation);
        $this->invitations->expire($invitation);

        return $this->backWithSuccess('Invitation expired.');
    }
}
