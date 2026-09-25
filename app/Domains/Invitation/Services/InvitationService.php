<?php

namespace App\Domains\Invitation\Services;

use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Invitation\Contracts\InvitationRepositoryInterface;
use App\Domains\Invitation\Contracts\InvitationServiceInterface;
use App\Domains\Invitation\Enums\InvitationStatus;
use App\Domains\Invitation\Events\InvitationResponded;
use App\Domains\Invitation\Events\InvitationSent;
use App\Domains\Invitation\Exceptions\GuestNoPhoneException;
use App\Domains\Invitation\Exceptions\GuestNotApprovedException;
use App\Domains\Invitation\Exceptions\InvitationActiveException;
use App\Domains\Invitation\Exceptions\InvitationExpiredException;
use App\Domains\Invitation\Exceptions\InvitationNotSendableException;
use App\Domains\Invitation\Models\Invitation;
use Illuminate\Database\UniqueConstraintViolationException;

class InvitationService extends BaseService implements InvitationServiceInterface
{
    public function __construct(
        private readonly InvitationRepositoryInterface $invitations,
    ) {}

    public function create(Guest $guest): Invitation
    {
        if ($guest->approval_status !== ApprovalStatus::Approved) {
            throw new GuestNotApprovedException;
        }

        if ($this->invitations->findActiveForGuest($guest->id)) {
            throw new InvitationActiveException;
        }

        try {
            /** @var Invitation */
            return $this->invitations->create([
                'event_id' => $guest->event_id,
                'guest_id' => $guest->id,
                'status' => InvitationStatus::Pending,
                'token' => bin2hex(random_bytes(32)),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Lost a race against another request creating one.
            throw new InvitationActiveException(previous: $e);
        }
    }

    public function send(Invitation $invitation): Invitation
    {
        if (! $invitation->status->canBeSent()) {
            throw new InvitationNotSendableException;
        }

        return $this->deliver($invitation);
    }

    public function resend(Invitation $invitation): Invitation
    {
        if (! $invitation->status->canBeResent()) {
            throw new InvitationNotSendableException('Only sent invitations can be re-sent.');
        }

        return $this->deliver($invitation);
    }

    public function expire(Invitation $invitation): Invitation
    {
        if (! $invitation->status->isActive()) {
            throw new InvitationNotSendableException('Only pending or sent invitations can be expired.');
        }

        /** @var Invitation */
        return $this->invitations->update($invitation, ['status' => InvitationStatus::Expired]);
    }

    public function accept(string $token): Invitation
    {
        return $this->respond($token, InvitationStatus::Accepted);
    }

    public function decline(string $token): Invitation
    {
        return $this->respond($token, InvitationStatus::Declined);
    }

    private function deliver(Invitation $invitation): Invitation
    {
        if ($invitation->guest->phone === null) {
            throw new GuestNoPhoneException;
        }

        return $this->transaction(function () use ($invitation) {
            $now = now();

            /** @var Invitation $invitation */
            $invitation = $this->invitations->update($invitation, [
                'status' => InvitationStatus::Sent,
                'sent_at' => $now,
                'expires_at' => $now->copy()->addDays(config('emp.invitation_expiry_days')),
            ]);

            InvitationSent::dispatch($invitation);

            return $invitation;
        });
    }

    private function respond(string $token, InvitationStatus $response): Invitation
    {
        $invitation = $this->invitations->findByToken($token)
            ?? throw new NotFoundException('Invitation not found.');

        // Idempotent: repeating (or changing) a response returns the current state.
        if ($invitation->status->isResponded()) {
            return $invitation;
        }

        if ($invitation->isExpired()) {
            if ($invitation->status !== InvitationStatus::Expired) {
                $this->invitations->update($invitation, ['status' => InvitationStatus::Expired]);
            }

            throw new InvitationExpiredException;
        }

        return $this->transaction(function () use ($invitation, $response) {
            /** @var Invitation $invitation */
            $invitation = $this->invitations->update($invitation, [
                'status' => $response,
                'responded_at' => now(),
            ]);

            InvitationResponded::dispatch($invitation);

            return $invitation;
        });
    }
}
