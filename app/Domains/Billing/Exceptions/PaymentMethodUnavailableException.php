<?php

namespace App\Domains\Billing\Exceptions;

use App\Core\Exceptions\ConflictException;

class PaymentMethodUnavailableException extends ConflictException
{
    protected string $errorCode = 'PAYMENT_METHOD_UNAVAILABLE';

    protected string $defaultMessage = 'This card can\'t be charged any more. Choose another card or add a new one.';
}
