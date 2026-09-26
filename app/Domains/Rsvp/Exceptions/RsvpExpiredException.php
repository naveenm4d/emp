<?php

namespace App\Domains\Rsvp\Exceptions;

use App\Core\Exceptions\GoneException;

class RsvpExpiredException extends GoneException
{
    protected string $errorCode = 'RSVP_EXPIRED';

    protected string $defaultMessage = 'This RSVP link has expired.';
}
