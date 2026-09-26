<?php

namespace App\Domains\Guest\Exceptions;

use App\Core\Exceptions\ConflictException;

class GuestLimitReachedException extends ConflictException
{
    protected string $errorCode = 'GUEST_LIMIT_REACHED';

    protected string $defaultMessage = 'This event has reached its guest limit.';
}
