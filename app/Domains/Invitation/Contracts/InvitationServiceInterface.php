<?php

namespace App\Domains\Invitation\Contracts;

use App\Domains\Guest\Models\Guest;
use App\Domains\Invitation\Models\Invitation;

interface InvitationServiceInterface
{
    public function create(Guest $guest): Invitation;

    public function send(Invitation $invitation): Invitation;

    public function resend(Invitation $invitation): Invitation;

    public function expire(Invitation $invitation): Invitation;

    public function accept(string $token): Invitation;

    public function decline(string $token): Invitation;
}
