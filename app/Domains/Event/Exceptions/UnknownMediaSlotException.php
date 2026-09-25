<?php

namespace App\Domains\Event\Exceptions;

use App\Core\Exceptions\UnprocessableException;

class UnknownMediaSlotException extends UnprocessableException
{
    protected string $errorCode = 'UNKNOWN_MEDIA_SLOT';

    protected string $defaultMessage = 'The event\'s template has no such media placeholder.';
}
