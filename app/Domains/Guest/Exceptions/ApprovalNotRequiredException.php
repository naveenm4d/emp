<?php

namespace App\Domains\Guest\Exceptions;

use App\Core\Exceptions\ConflictException;

class ApprovalNotRequiredException extends ConflictException
{
    protected string $errorCode = 'APPROVAL_NOT_REQUIRED';

    protected string $defaultMessage = 'This event does not use guest approval.';
}
