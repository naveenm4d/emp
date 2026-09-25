<?php

namespace App\Core\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base for every business-rule violation raised by a service.
 *
 * Rendered as a flash error after an Inertia mutation, or as the matching
 * HTTP error page on a page load (see bootstrap/app.php).
 */
abstract class DomainException extends RuntimeException
{
    protected int $status = 400;

    protected string $errorCode = 'BAD_REQUEST';

    protected string $defaultMessage = 'The request could not be completed.';

    public function __construct(?string $message = null, ?Throwable $previous = null)
    {
        parent::__construct($message ?? $this->defaultMessage, 0, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
