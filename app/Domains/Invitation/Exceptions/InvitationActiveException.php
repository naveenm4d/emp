<?php

namespace App\Domains\Invitation\Exceptions;

use App\Core\Exceptions\ConflictException;

class InvitationActiveException extends ConflictException
{
    protected string $errorCode = 'INVITATION_ACTIVE';

    protected string $defaultMessage = 'This guest already has an active invitation.';
}
