<?php

namespace App\Domains\Client\Events;

use App\Domains\Client\Models\Client;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Staff changed a client's plan by hand (not a purchase). Billing stops or
 * moves the client's subscription so nobody is charged for a plan they're
 * no longer on.
 */
final class ClientPlanChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Client $client,
    ) {}
}
