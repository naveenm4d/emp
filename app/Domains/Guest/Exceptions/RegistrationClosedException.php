<?php

namespace App\Domains\Guest\Exceptions;

use App\Core\Exceptions\ForbiddenException;

class RegistrationClosedException extends ForbiddenException
{
    protected string $errorCode = 'REGISTRATION_CLOSED';

    protected string $defaultMessage = 'Registration for this event is closed.';
}
