<?php

namespace App\Domains\Invitation\Exceptions;

use App\Core\Exceptions\ConflictException;

class GuestNotApprovedException extends ConflictException
{
    protected string $errorCode = 'GUEST_NOT_APPROVED';

    protected string $defaultMessage = 'Only approved guests can be invited.';
}
