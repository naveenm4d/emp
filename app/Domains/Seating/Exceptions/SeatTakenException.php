<?php

namespace App\Domains\Seating\Exceptions;

use App\Core\Exceptions\ConflictException;

class SeatTakenException extends ConflictException
{
    protected string $errorCode = 'SEAT_TAKEN';

    protected string $defaultMessage = 'Someone already sits in this seat.';
}
