<?php

namespace App\Domains\Event\Exceptions;

use App\Core\Exceptions\ConflictException;
use App\Domains\Event\Enums\EventState;

class InvalidEventStateTransitionException extends ConflictException
{
    protected string $errorCode = 'INVALID_STATE_TRANSITION';

    public static function between(EventState $from, EventState $to): self
    {
        return new self("An event cannot move from {$from->value} to {$to->value}.");
    }
}
