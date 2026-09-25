<?php

namespace App\Domains\Guest\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Guest\Contracts\GuestServiceInterface;
use App\Domains\Guest\Models\Guest;
use Illuminate\Http\RedirectResponse;

class GuestApprovalController extends InertiaController
{
    public function __construct(
        private readonly GuestServiceInterface $guests,
    ) {}

    public function approve(Guest $guest): RedirectResponse
    {
        $this->authorize('update', $guest);
        $this->guests->approve($guest);

        return $this->backWithSuccess("{$guest->name} approved.");
    }

    public function reject(Guest $guest): RedirectResponse
    {
        $this->authorize('update', $guest);
        $this->guests->reject($guest);

        return $this->backWithSuccess("{$guest->name} rejected.");
    }

    public function waitlist(Guest $guest): RedirectResponse
    {
        $this->authorize('update', $guest);
        $this->guests->waitlist($guest);

        return $this->backWithSuccess("{$guest->name} waitlisted.");
    }
}
