<?php

namespace App\Core\Exceptions;

class ForbiddenException extends DomainException
{
    protected int $status = 403;

    protected string $errorCode = 'FORBIDDEN';

    protected string $defaultMessage = 'You are not allowed to perform this action.';
}
