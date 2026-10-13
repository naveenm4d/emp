<?php

namespace App\Domains\Billing\Exceptions;

use App\Core\Exceptions\ConflictException;

class PurchaseNotAllowedException extends ConflictException
{
    protected string $errorCode = 'PURCHASE_NOT_ALLOWED';

    protected string $defaultMessage = 'This can\'t be bought on your plan.';
}
