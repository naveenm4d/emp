<?php

namespace App\Domains\Guest\Exceptions;

use App\Core\Exceptions\ConflictException;

class EventAtCapacityException extends ConflictException
{
    protected string $errorCode = 'EVENT_AT_CAPACITY';

    protected string $defaultMessage = 'This event has reached its capacity.';
}
