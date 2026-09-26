<?php

namespace App\Domains\Rsvp\Exceptions;

use App\Core\Exceptions\ConflictException;

class MessageLimitReachedException extends ConflictException
{
    protected string $errorCode = 'MESSAGE_LIMIT_REACHED';

    protected string $defaultMessage = 'This guest has had all the messages allowed for this event.';
}
