<?php

namespace App\Domains\Event\Http\Requests\Admin;

use App\Domains\Client\Models\Client;
use App\Domains\Event\Http\Requests\Dashboard\StoreEventRequest as DashboardStoreEventRequest;

/** Staff creating an event on behalf of the client in the route. */
class StoreEventRequest extends DashboardStoreEventRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:events.create` on the route
    }

    protected function client(): ?Client
    {
        $client = $this->route('client');

        return $client instanceof Client ? $client : null;
    }
}
