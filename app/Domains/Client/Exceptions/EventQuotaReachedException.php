<?php

namespace App\Domains\Client\Exceptions;

use App\Core\Exceptions\ConflictException;

class EventQuotaReachedException extends ConflictException
{
    protected string $errorCode = 'EVENT_QUOTA_REACHED';

    protected string $defaultMessage = 'Your plan doesn\'t allow another event.';
}
