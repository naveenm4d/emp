<?php

namespace App\Domains\Billing\Exceptions;

use App\Core\Exceptions\ConflictException;

class PriceUnavailableException extends ConflictException
{
    protected string $errorCode = 'PRICE_UNAVAILABLE';

    protected string $defaultMessage = 'This isn\'t for sale right now.';
}
