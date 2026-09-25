<?php

namespace App\Domains\Event\Exceptions;

use App\Core\Exceptions\ConflictException;

class EventSlugTakenException extends ConflictException
{
    protected string $errorCode = 'EVENT_SLUG_TAKEN';

    protected string $defaultMessage = 'This event URL is already taken.';
}
