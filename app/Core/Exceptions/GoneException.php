<?php

namespace App\Core\Exceptions;

class GoneException extends DomainException
{
    protected int $status = 410;

    protected string $errorCode = 'GONE';

    protected string $defaultMessage = 'The resource is no longer available.';
}
