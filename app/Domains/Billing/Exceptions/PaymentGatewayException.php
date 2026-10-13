<?php

namespace App\Domains\Billing\Exceptions;

use App\Core\Exceptions\DomainException;

class PaymentGatewayException extends DomainException
{
    protected int $status = 502;

    protected string $errorCode = 'PAYMENT_GATEWAY_ERROR';

    protected string $defaultMessage = 'We couldn\'t reach PayHere. Nothing was charged; try again in a moment.';
}
