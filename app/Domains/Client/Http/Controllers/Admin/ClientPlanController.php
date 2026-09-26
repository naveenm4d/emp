<?php

namespace App\Domains\Client\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\Http\Requests\Admin\UpdateClientPlanRequest;
use App\Domains\Client\Models\Client;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Http\RedirectResponse;

/**
 * Staff set a client's plan, its end date and event credits after payment
 * (there is no online payment yet). Every change is logged with a note.
 */
class ClientPlanController extends InertiaController
{
    public function __construct(
        private readonly ClientPlanServiceInterface $plans,
    ) {}

    public function update(UpdateClientPlanRequest $request, Client $client): RedirectResponse
    {
        /** @var StaffMember $staff */
        $staff = $request->user('staff');

        $client = $this->plans->changePlan($client, $request->toData(), $staff, $request->note());

        return $this->backWithSuccess("{$client->name} is now on the {$client->plan->label()} plan.");
    }
}
