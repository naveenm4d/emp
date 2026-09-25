<?php

namespace App\Domains\Notification\Exceptions;

use App\Core\Exceptions\ConflictException;

class NotificationNotRetryableException extends ConflictException
{
    protected string $errorCode = 'NOTIFICATION_NOT_RETRYABLE';

    protected string $defaultMessage = 'Only failed notifications can be retried.';
}
