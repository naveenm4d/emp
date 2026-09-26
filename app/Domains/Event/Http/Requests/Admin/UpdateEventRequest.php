<?php

namespace App\Domains\Event\Http\Requests\Admin;

use App\Domains\Client\Models\Client;
use App\Domains\Event\Http\Requests\Dashboard\UpdateEventRequest as DashboardUpdateEventRequest;

/** Staff editing an event of the client in the route. */
class UpdateEventRequest extends DashboardUpdateEventRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:events.update` on the route
    }

    protected function client(): ?Client
    {
        $client = $this->route('client');

        return $client instanceof Client ? $client : null;
    }
}
