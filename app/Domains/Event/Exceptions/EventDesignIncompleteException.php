<?php

namespace App\Domains\Event\Exceptions;

use App\Core\Exceptions\UnprocessableException;

class EventDesignIncompleteException extends UnprocessableException
{
    protected string $errorCode = 'EVENT_DESIGN_INCOMPLETE';

    protected string $defaultMessage = 'Upload every required media file in the design before publishing.';

    /** @param list<string> $missing slot labels */
    public static function missing(array $missing): self
    {
        return new self('Upload every required media file before publishing. Missing: '.implode(', ', $missing).'.');
    }
}
