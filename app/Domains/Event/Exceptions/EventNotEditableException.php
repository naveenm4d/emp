<?php

namespace App\Domains\Event\Exceptions;

use App\Core\Exceptions\ConflictException;

class EventNotEditableException extends ConflictException
{
    protected string $errorCode = 'EVENT_NOT_EDITABLE';

    protected string $defaultMessage = 'Cancelled events can no longer be changed.';
}
