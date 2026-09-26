<?php

namespace App\Domains\Event\Exceptions;

use App\Core\Exceptions\UnprocessableException;

class EventSlugReservedException extends UnprocessableException
{
    protected string $errorCode = 'EVENT_SLUG_RESERVED';

    protected string $defaultMessage = 'This URL is reserved; choose another slug.';
}
