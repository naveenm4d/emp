<?php

namespace App\Domains\Rsvp\Services;

use App\Core\Exceptions\DomainException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Domains\Guest\DTOs\RegistrationDetailsData;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Notification\Enums\NotificationKind;
use App\Domains\Rsvp\Contracts\RsvpRepositoryInterface;
use App\Domains\Rsvp\Contracts\RsvpServiceInterface;
use App\Domains\Rsvp\Enums\RsvpReminderType;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Events\RsvpReminded;
use App\Domains\Rsvp\Events\RsvpResponded;
use App\Domains\Rsvp\Events\RsvpSent;
use App\Domains\Rsvp\Exceptions\GuestNoPhoneException;
use App\Domains\Rsvp\Exceptions\GuestNotApprovedException;
use App\Domains\Rsvp\Exceptions\MessageLimitReachedException;
use App\Domains\Rsvp\Exceptions\RsvpActiveException;
use App\Domains\Rsvp\Exceptions\RsvpExpiredException;
use App\Domains\Rsvp\Exceptions\RsvpNotSendableException;
use App\Domains\Rsvp\Exceptions\RsvpResponseNotAllowedException;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Database\UniqueConstraintViolationException;

class RsvpService extends BaseService implements RsvpServiceInterface
{
    public function __construct(
        private readonly RsvpRepositoryInterface $rsvps,
        private readonly NotificationQueryServiceInterface $notifications,
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
            $this->rsvps->findAndLock($rsvp->id);
            $this->ensureAllowance($rsvp, NotificationKind::RsvpReminder);

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

            // Guests who've had all their reminders are skipped quietly.
            if ($due === [] || ! $this->hasAllowance($rsvp, NotificationKind::RsvpReminder)) {
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

    /** The guest may still get a message of this kind (failed messages don't count). */
    private function hasAllowance(Rsvp $rsvp, NotificationKind $kind): bool
    {
        $limit = $kind === NotificationKind::RsvpInvitation ? $rsvp->event->invitationLimit() : $rsvp->event->reminderLimit();

        return $this->notifications->countSentToGuest($rsvp->guest_id, $kind) < $limit;
    }

    private function ensureAllowance(Rsvp $rsvp, NotificationKind $kind): void
    {
        if ($this->hasAllowance($rsvp, $kind)) {
            return;
        }

        $invitation = $kind === NotificationKind::RsvpInvitation;
        $limit = $invitation ? $rsvp->event->invitationLimit() : $rsvp->event->reminderLimit();
        $what = str($invitation ? 'invitation' : 'reminder')->plural($limit);

        throw new MessageLimitReachedException(
            $limit === 0
                ? "No {$what} can be sent to guests of this event."
                : "{$rsvp->guest->name} has had all {$limit} {$what} for this event.",
        );
    }

    private function deliver(Rsvp $rsvp): Rsvp
    {
        if ($rsvp->guest->phone === null) {
            throw new GuestNoPhoneException;
        }

        return $this->transaction(function () use ($rsvp) {
            // The lock keeps a double click from going over the limit.
            $this->rsvps->findAndLock($rsvp->id);
            $this->ensureAllowance($rsvp, NotificationKind::RsvpInvitation);

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

    public function respond(string $token, RsvpStatus $response, ?RegistrationDetailsData $details = null, ?string $note = null): Rsvp
    {
        $rsvp = $this->rsvps->findByToken($token)
            ?? throw new NotFoundException('RSVP link not found.');

        if (! $response->isResponded() || ($response === RsvpStatus::Maybe && ! $rsvp->event->registrationSettings()->allowMaybe)) {
            throw new RsvpResponseNotAllowedException;
        }

        if ($rsvp->status->isResponded()) {
            // Answers are final unless the event lets guests change them (until its lock time).
            if (! $rsvp->canChangeResponse()) {
                return $rsvp;
            }
        } elseif ($rsvp->isExpired()) {
            if ($rsvp->status !== RsvpStatus::Expired) {
                $this->rsvps->update($rsvp, ['status' => RsvpStatus::Expired]);
            }

            throw new RsvpExpiredException;
        }

        return $this->transaction(function () use ($rsvp, $response, $details, $note) {
            /** @var Rsvp $rsvp */
            $rsvp = $this->rsvps->update($rsvp, [
                'status' => $response,
                'responded_at' => now(),
                // Only a decline carries a note; a later answer clears it.
                'response_note' => $response === RsvpStatus::Declined ? $note : null,
            ]);

            RsvpResponded::dispatch($rsvp, $details);

            return $rsvp;
        });
    }
}
