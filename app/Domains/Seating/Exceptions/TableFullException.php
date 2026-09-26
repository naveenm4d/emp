<?php

namespace App\Domains\Seating\Exceptions;

use App\Core\Exceptions\ConflictException;

class TableFullException extends ConflictException
{
    protected string $errorCode = 'TABLE_FULL';

    protected string $defaultMessage = "There aren't enough free seats at this table for the whole party.";
}
