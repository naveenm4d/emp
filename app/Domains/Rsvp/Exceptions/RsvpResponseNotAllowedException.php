<?php

namespace App\Domains\Rsvp\Exceptions;

use App\Core\Exceptions\UnprocessableException;

class RsvpResponseNotAllowedException extends UnprocessableException
{
    protected string $errorCode = 'RSVP_RESPONSE_NOT_ALLOWED';

    protected string $defaultMessage = 'This event does not accept that answer.';
}
