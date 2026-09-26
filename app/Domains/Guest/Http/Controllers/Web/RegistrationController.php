<?php

namespace App\Domains\Guest\Http\Controllers\Web;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Contracts\EventLinkServiceInterface;
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
        private readonly EventLinkServiceInterface $links,
    ) {}

    /** Posted from the event's public URL (domain/{slug}/{code}/register). */
    public function store(RegisterGuestRequest $request, string $slug, string $code): RedirectResponse
    {
        $link = $this->links->resolve($code);

        if ($link->isForGuest()) {
            throw new NotFoundException('Link not found.');
        }

        $guest = $this->guests->registerPublic($link->event, $request->toData());

        return $this->backWithSuccess($guest->approval_status === ApprovalStatus::Pending
            ? "Thanks, {$guest->name}! Your registration is awaiting the host's approval."
            : "You're registered, {$guest->name}. See you there!");
    }
}
