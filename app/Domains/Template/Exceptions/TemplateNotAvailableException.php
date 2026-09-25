<?php

namespace App\Domains\Template\Exceptions;

use App\Core\Exceptions\UnprocessableException;

class TemplateNotAvailableException extends UnprocessableException
{
    protected string $errorCode = 'TEMPLATE_NOT_AVAILABLE';

    protected string $defaultMessage = 'This template is not available.';
}
