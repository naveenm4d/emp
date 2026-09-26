<?php

namespace App\Domains\Seating\Exceptions;

use App\Core\Exceptions\UnprocessableException;

class TableNameTakenException extends UnprocessableException
{
    protected string $errorCode = 'TABLE_NAME_TAKEN';

    protected string $defaultMessage = 'This event already has a table with that name.';
}
