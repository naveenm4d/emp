<?php

namespace App\Domains\Billing\Exceptions;

use App\Core\Exceptions\ConflictException;

class SubscriptionStateException extends ConflictException
{
    protected string $errorCode = 'SUBSCRIPTION_STATE';

    protected string $defaultMessage = 'The subscription can\'t be changed now.';
}
