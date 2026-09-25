<?php

namespace App\Domains\Template\Exceptions;

use App\Core\Exceptions\ConflictException;

class TemplateVersionExistsException extends ConflictException
{
    protected string $errorCode = 'TEMPLATE_VERSION_EXISTS';

    protected string $defaultMessage = 'This template version already exists with different code. Template versions are immutable: bump the version.';
}
