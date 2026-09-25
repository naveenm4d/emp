<?php

namespace App\Core\Exceptions;

class BadRequestException extends DomainException
{
    protected int $status = 400;

    protected string $errorCode = 'BAD_REQUEST';

    protected string $defaultMessage = 'The request could not be completed.';
}
