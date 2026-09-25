<?php

namespace App\Domains\Guest\Http\Controllers\Site;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Guest\Contracts\GuestServiceInterface;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Http\Requests\Site\RegisterGuestRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Self-registration from the public event page. Closed registration,
 * capacity and duplicate contacts come back as flash errors through the
 * DomainException handler in bootstrap/app.php.
 */
class RegistrationController extends InertiaController
{
    public function __construct(
        private readonly GuestServiceInterface $guests,
    ) {}

    public function store(RegisterGuestRequest $request, string $slug): RedirectResponse
    {
        $guest = $this->guests->registerPublic($slug, $request->toData());

        return $this->backWithSuccess($guest->approval_status === ApprovalStatus::Pending
            ? "Thanks, {$guest->name}! Your registration is awaiting the host's approval."
            : "You're registered, {$guest->name}. See you there!");
    }
}
