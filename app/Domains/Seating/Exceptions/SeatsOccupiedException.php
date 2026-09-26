<?php

namespace App\Domains\Seating\Exceptions;

use App\Core\Exceptions\ConflictException;

class SeatsOccupiedException extends ConflictException
{
    protected string $errorCode = 'SEATS_OCCUPIED';

    protected string $defaultMessage = 'Guests sit in the seats you want to remove. Move them first.';
}
