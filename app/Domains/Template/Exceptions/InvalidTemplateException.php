<?php

namespace App\Domains\Template\Exceptions;

use App\Core\Exceptions\UnprocessableException;

/**
 * Template code or its package failed validation (syntax, unsafe markup,
 * missing files). Raised at import time, never while guests are viewing.
 */
class InvalidTemplateException extends UnprocessableException
{
    protected string $errorCode = 'INVALID_TEMPLATE';

    protected string $defaultMessage = 'The template is invalid.';
}
