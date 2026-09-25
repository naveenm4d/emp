<?php

namespace App\Domains\Guest\Exceptions;

use App\Core\Exceptions\ConflictException;

class GuestPhoneConflictException extends ConflictException
{
    protected string $errorCode = 'GUEST_PHONE_CONFLICT';

    protected string $defaultMessage = 'A guest with this phone number is already registered for this event.';
}
