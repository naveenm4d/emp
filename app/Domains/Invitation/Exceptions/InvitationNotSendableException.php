<?php

namespace App\Domains\Invitation\Exceptions;

use App\Core\Exceptions\ConflictException;

class InvitationNotSendableException extends ConflictException
{
    protected string $errorCode = 'INVITATION_NOT_SENDABLE';

    protected string $defaultMessage = 'This invitation cannot be sent in its current state.';
}
