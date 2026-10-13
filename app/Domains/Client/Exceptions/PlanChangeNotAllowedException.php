<?php

namespace App\Domains\Client\Exceptions;

use App\Core\Exceptions\ConflictException;

class PlanChangeNotAllowedException extends ConflictException
{
    protected string $errorCode = 'PLAN_CHANGE_NOT_ALLOWED';

    protected string $defaultMessage = 'Your plan can\'t be changed this way.';
}
