<?php

namespace App\Domains\Template\Exceptions;

use App\Core\Exceptions\ConflictException;

class TemplateInUseException extends ConflictException
{
    protected string $errorCode = 'TEMPLATE_IN_USE';

    protected string $defaultMessage = 'Events use this template, so it cannot be deleted. Deactivate it instead.';
}
