<?php

namespace App\Domains\Event\Exceptions;

use App\Core\Exceptions\ConflictException;

class RegistrationNotAvailableException extends ConflictException
{
    protected string $errorCode = 'REGISTRATION_NOT_AVAILABLE';

    protected string $defaultMessage = 'Guest-list-only events have no public registration.';
}
