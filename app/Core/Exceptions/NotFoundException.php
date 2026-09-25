<?php

namespace App\Core\Exceptions;

class NotFoundException extends DomainException
{
    protected int $status = 404;

    protected string $errorCode = 'NOT_FOUND';

    protected string $defaultMessage = 'Resource not found.';
}
