<?php

namespace App\Domains\Rsvp\Exceptions;

use App\Core\Exceptions\ConflictException;

class RsvpNotSendableException extends ConflictException
{
    protected string $errorCode = 'RSVP_NOT_SENDABLE';

    protected string $defaultMessage = 'This RSVP link cannot be sent in its current state.';
}
