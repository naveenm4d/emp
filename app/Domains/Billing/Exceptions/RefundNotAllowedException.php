<?php

namespace App\Domains\Billing\Exceptions;

use App\Core\Exceptions\ConflictException;

class RefundNotAllowedException extends ConflictException
{
    protected string $errorCode = 'REFUND_NOT_ALLOWED';

    protected string $defaultMessage = 'Only a paid payment can be refunded.';
}
