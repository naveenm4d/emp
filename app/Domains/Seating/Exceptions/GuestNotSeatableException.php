<?php

namespace App\Domains\Seating\Exceptions;

use App\Core\Exceptions\UnprocessableException;

class GuestNotSeatableException extends UnprocessableException
{
    protected string $errorCode = 'GUEST_NOT_SEATABLE';

    protected string $defaultMessage = "Only approved guests who haven't declined can be seated.";
}
