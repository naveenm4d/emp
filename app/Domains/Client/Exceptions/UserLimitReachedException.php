<?php

namespace App\Domains\Client\Exceptions;

use App\Core\Exceptions\ConflictException;

class UserLimitReachedException extends ConflictException
{
    protected string $errorCode = 'USER_LIMIT_REACHED';

    protected string $defaultMessage = 'Your plan doesn\'t allow another user.';
}
