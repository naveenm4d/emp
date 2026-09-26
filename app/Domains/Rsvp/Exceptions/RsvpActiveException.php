<?php

namespace App\Domains\Rsvp\Exceptions;

use App\Core\Exceptions\ConflictException;

class RsvpActiveException extends ConflictException
{
    protected string $errorCode = 'RSVP_ACTIVE';

    protected string $defaultMessage = 'This guest already has an active RSVP link.';
}
