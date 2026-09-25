<?php

namespace App\Core\Services;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Base class for domain services. Services own every business rule and
 * throw DomainException subclasses when a rule is violated.
 */
abstract class BaseService
{
    /**
     * Run the callback inside a database transaction.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @param  int<1, max>  $attempts
     * @return TReturn
     */
    protected function transaction(Closure $callback, int $attempts = 1): mixed
    {
        return DB::transaction($callback, $attempts);
    }
}
