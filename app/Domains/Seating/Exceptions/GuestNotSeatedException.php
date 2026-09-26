<?php

namespace App\Domains\Seating\Exceptions;

use App\Core\Exceptions\UnprocessableException;

class GuestNotSeatedException extends UnprocessableException
{
    protected string $errorCode = 'GUEST_NOT_SEATED';

    protected string $defaultMessage = "This guest doesn't have a seat yet.";
}
