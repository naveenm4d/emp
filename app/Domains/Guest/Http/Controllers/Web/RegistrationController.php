<?php

namespace App\Domains\Guest\Http\Controllers\Web;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Guest\Contracts\GuestServiceInterface;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Http\Requests\Web\RegisterGuestRequest;
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

    /** Posted from the event's public URL (domain/{slug}/{code}/register). */
    public function store(RegisterGuestRequest $request, string $slug, string $code): RedirectResponse
    {
        $guest = $this->guests->registerPublic($request->link()->event, $request->toData(), $request->details());

        return $this->backWithSuccess($guest->approval_status === ApprovalStatus::Pending
            ? "Thanks, {$guest->name}! Your registration is awaiting the host's approval."
            : "You're registered, {$guest->name}. See you there!");
    }
}
