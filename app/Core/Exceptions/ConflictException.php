<?php

namespace App\Core\Exceptions;

class ConflictException extends DomainException
{
    protected int $status = 409;

    protected string $errorCode = 'CONFLICT';

    protected string $defaultMessage = 'The request conflicts with the current state.';
}
