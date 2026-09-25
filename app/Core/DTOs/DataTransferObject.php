<?php

namespace App\Core\DTOs;

/**
 * Immutable, fully-populated input passed from controllers into services.
 */
abstract readonly class DataTransferObject
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
