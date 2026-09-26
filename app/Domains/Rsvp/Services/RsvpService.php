<?php

namespace App\Domains\Rsvp\Services;

use App\Core\Exceptions\DomainException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Contracts\RsvpRepositoryInterface;
use App\Domains\Rsvp\Contracts\RsvpServiceInterface;
use App\Domains\Rsvp\Enums\RsvpReminderType;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Events\RsvpReminded;
use App\Domains\Rsvp\Events\RsvpResponded;
use App\Domains\Rsvp\Events\RsvpSent;
use App\Domains\Rsvp\Exceptions\GuestNoPhoneException;
use App\Domains\Rsvp\Exceptions\GuestNotApprovedException;
use App\Domains\Rsvp\Exceptions\RsvpActiveException;
use App\Domains\Rsvp\Exceptions\RsvpExpiredException;
use App\Domains\Rsvp\Exceptions\RsvpNotSendableException;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Database\UniqueConstraintViolationException;

class RsvpService extends BaseService implements RsvpServiceInterface
{
    public function __construct(
        private readonly RsvpRepositoryInterface $rsvps,
    ) {}

    public function create(Guest $guest): Rsvp
    {
        if ($guest->approval_status !== ApprovalStatus::Approved) {
            throw new GuestNotApprovedException;
        }

        if ($this->rsvps->findActiveForGuest($guest->id)) {
            throw new RsvpActiveException;
        }

        try {
            /** @var Rsvp */
            return $this->rsvps->create([
                'event_id' => $guest->event_id,
                'guest_id' => $guest->id,
                'status' => RsvpStatus::Pending,
                'token' => bin2hex(random_bytes(32)),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Lost a race against another request creating one.
            throw new RsvpActiveException(previous: $e);
        }
    }

    public function send(Rsvp $rsvp): Rsvp
    {
        if (! $rsvp->status->canBeSent()) {
            throw new RsvpNotSendableException;
        }

        return $this->deliver($rsvp);
    }

    public function resend(Rsvp $rsvp): Rsvp
    {
        if (! $rsvp->status->canBeResent()) {
            throw new RsvpNotSendableException('Only sent RSVP links can be re-sent.');
        }

        return $this->deliver($rsvp);
    }

    public function expire(Rsvp $rsvp): Rsvp
    {
        if (! $rsvp->status->isActive()) {
            throw new RsvpNotSendableException('Only pending or sent RSVP links can be expired.');
        }

        /** @var Rsvp */
        return $this->rsvps->update($rsvp, ['status' => RsvpStatus::Expired]);
    }

    public function remind(Rsvp $rsvp, RsvpReminderType ...$automatic): Rsvp
    {
        if ($rsvp->status !== RsvpStatus::Sent || $rsvp->isExpired()) {
            throw new RsvpNotSendableException('Only sent, unanswered RSVP links can be reminded.');
        }

        if ($rsvp->guest->phone === null) {
            throw new GuestNoPhoneException;
        }

        return $this->transaction(function () use ($rsvp, $automatic) {
            $now = now();
            $attributes = ['reminder_count' => $rsvp->reminder_count + 1, 'last_reminded_at' => $now];

            foreach ($automatic as $type) {
                $attributes[$type->column()] = $now;
            }

            /** @var Rsvp $rsvp */
            $rsvp = $this->rsvps->update($rsvp, $attributes);

            RsvpReminded::dispatch($rsvp);

            return $rsvp;
        });
    }

    public function sendDueReminders(): int
    {
        $sent = 0;

        foreach ($this->rsvps->candidatesForAutoReminder() as $rsvp) {
            $due = $this->dueReminders($rsvp);

            if ($due === []) {
                continue;
            }

            try {
                // Both due at once: one message fulfils both.
                $this->remind($rsvp, ...$due);
                $sent++;
            } catch (DomainException $e) {
                report($e);
            }
        }

        return $sent;
    }

    public function accept(string $token): Rsvp
    {
        return $this->respond($token, RsvpStatus::Accepted);
    }

    public function decline(string $token): Rsvp
    {
        return $this->respond($token, RsvpStatus::Declined);
    }

    /**
     * The automatic reminders a link is due for. Nothing goes out within a day
     * of the link being sent or of the last reminder, so guests are never
     * messaged twice in a row.
     *
     * @return list<RsvpReminderType>
     */
    private function dueReminders(Rsvp $rsvp): array
    {
        $now = now();
        $dayAgo = $now->copy()->subDay();

        if ($rsvp->sent_at?->isAfter($dayAgo) || $rsvp->last_reminded_at?->isAfter($dayAgo)) {
            return [];
        }

        $due = [];

        if ($rsvp->auto_after_reminded_at === null
            && $rsvp->sent_at?->lte($now->copy()->subDays($rsvp->event->remind_after_days))) {
            $due[] = RsvpReminderType::AfterSent;
        }

        $eventDate = $rsvp->event->event_date;

        if ($rsvp->auto_before_reminded_at === null && $eventDate !== null
            && $now->copy()->startOfDay()->between($eventDate->copy()->subDays($rsvp->event->remind_before_days), $eventDate)) {
            $due[] = RsvpReminderType::BeforeEvent;
        }

        return $due;
    }

    private function deliver(Rsvp $rsvp): Rsvp
    {
        if ($rsvp->guest->phone === null) {
            throw new GuestNoPhoneException;
        }

        return $this->transaction(function () use ($rsvp) {
            $now = now();

            /** @var Rsvp $rsvp */
            $rsvp = $this->rsvps->update($rsvp, [
                'status' => RsvpStatus::Sent,
                'sent_at' => $now,
                'expires_at' => $now->copy()->addDays(config('emp.rsvp_expiry_days')),
            ]);

            RsvpSent::dispatch($rsvp);

            return $rsvp;
        });
    }

    private function respond(string $token, RsvpStatus $response): Rsvp
    {
        $rsvp = $this->rsvps->findByToken($token)
            ?? throw new NotFoundException('RSVP link not found.');

        // Idempotent: repeating (or changing) a response returns the current state.
        if ($rsvp->status->isResponded()) {
            return $rsvp;
        }

        if ($rsvp->isExpired()) {
            if ($rsvp->status !== RsvpStatus::Expired) {
                $this->rsvps->update($rsvp, ['status' => RsvpStatus::Expired]);
            }

            throw new RsvpExpiredException;
        }

        return $this->transaction(function () use ($rsvp, $response) {
            /** @var Rsvp $rsvp */
            $rsvp = $this->rsvps->update($rsvp, [
                'status' => $response,
                'responded_at' => now(),
            ]);

            RsvpResponded::dispatch($rsvp);

            return $rsvp;
        });
    }
}
