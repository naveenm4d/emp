<?php

namespace App\Domains\Guest\Exceptions;

use App\Core\Exceptions\ConflictException;

class GuestEmailConflictException extends ConflictException
{
    protected string $errorCode = 'GUEST_EMAIL_CONFLICT';

    protected string $defaultMessage = 'A guest with this email is already registered for this event.';
}
