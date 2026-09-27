<?php

namespace App\Domains\Client\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use Inertia\Inertia;
use Inertia\Response;

/** The client's membership: plan, what's used / left and what's included (the plan comes from the shared `auth.client.plan`). */
class MembershipController extends InertiaController
{
    public function __invoke(): Response
    {
        return Inertia::render('client/membership');
    }
}
