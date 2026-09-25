<?php

namespace App\Core\Exceptions;

class UnprocessableException extends DomainException
{
    protected int $status = 422;

    protected string $errorCode = 'UNPROCESSABLE';

    protected string $defaultMessage = 'The request could not be processed.';
}
