<?php

namespace App\Domains\Billing\Exceptions;

use App\Core\Exceptions\UnprocessableException;

class BillingDetailsMissingException extends UnprocessableException
{
    protected string $errorCode = 'BILLING_DETAILS_MISSING';

    protected string $defaultMessage = 'Add your billing details (phone, address and city) before paying.';
}
