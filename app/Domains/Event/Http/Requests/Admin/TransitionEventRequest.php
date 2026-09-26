<?php

namespace App\Domains\Event\Http\Requests\Admin;

use App\Domains\Event\Http\Requests\Dashboard\TransitionEventRequest as DashboardTransitionEventRequest;

class TransitionEventRequest extends DashboardTransitionEventRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:events.update` on the route
    }
}
