<?php

namespace App\Domains\Guest\Services;

use App\Core\Services\BaseService;
use App\Domains\Event\Contracts\EventLinkServiceInterface;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Contracts\GuestRepositoryInterface;
use App\Domains\Guest\Contracts\GuestServiceInterface;
use App\Domains\Guest\Contracts\RegistrationAnswerRepositoryInterface;
use App\Domains\Guest\DTOs\GuestData;
use App\Domains\Guest\DTOs\RegistrationDetailsData;
use App\Domains\Guest\DTOs\UpdateGuestData;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Enums\GuestSource;
use App\Domains\Guest\Events\GuestPartyChanged;
use App\Domains\Guest\Exceptions\ApprovalNotRequiredException;
use App\Domains\Guest\Exceptions\EventAtCapacityException;
use App\Domains\Guest\Exceptions\GuestEmailConflictException;
use App\Domains\Guest\Exceptions\GuestPhoneConflictException;
use App\Domains\Guest\Exceptions\RegistrationClosedException;
use App\Domains\Guest\Models\Guest;
use Illuminate\Database\UniqueConstraintViolationException;

class GuestService extends BaseService implements GuestServiceInterface
{
    public function __construct(
        private readonly GuestRepositoryInterface $guests,
        private readonly EventQueryServiceInterface $events,
        private readonly EventLinkServiceInterface $links,
        private readonly RegistrationAnswerRepositoryInterface $answers,
    ) {}

    public function add(Event $event, GuestData $data): Guest
    {
        return $this->createGuest($event->id, $data, GuestSource::Manual);
    }

    public function registerPublic(Event $event, GuestData $data, ?RegistrationDetailsData $details = null): Guest
    {
        if (! $event->acceptsPublicRegistrations()) {
            throw new RegistrationClosedException;
        }

        return $this->createGuest($event->id, $data, GuestSource::PublicLink, $details);
    }

    public function saveRegistrationDetails(string $guestId, RegistrationDetailsData $details): Guest
    {
        return $this->transaction(function () use ($guestId, $details) {
            /** @var Guest $guest */
            $guest = $this->guests->findOrFail($guestId);

            if ($details->profile !== []) {
                // Guests can correct their email / phone; they must stay unique within the event.
                $this->ensureUniqueContact(
                    $guest->event_id,
                    $details->profile['email'] ?? null,
                    $details->profile['phone'] ?? null,
                    $guest->id,
                );

                /** @var Guest $guest */
                $guest = $this->persist(fn () => $this->guests->update($guest, $details->profile));
                $this->announcePartyChange($guest);
            }

            $this->answers->sync($guest->id, $details->answers);

            return $guest;
        });
    }

    public function update(Guest $guest, UpdateGuestData $data): Guest
    {
        $this->ensureUniqueContact(
            $guest->event_id,
            $data->has('email') ? $data->get('email') : null,
            $data->has('phone') ? $data->get('phone') : null,
            $guest->id,
        );

        /** @var Guest $guest */
        $guest = $this->persist(fn () => $this->guests->update($guest, [
            ...$data->toArray(),
            ...$this->invitedParty($guest, $data),
        ]));

        $this->announcePartyChange($guest);

        return $guest;
    }

    public function delete(Guest $guest): void
    {
        $this->guests->delete($guest);
    }

    public function approve(Guest $guest): Guest
    {
        // Guest-list-only events have no approval; their guests are always approved.
        if ($guest->event->registration_type === RegistrationType::GuestListOnly) {
            throw new ApprovalNotRequiredException;
        }

        return $this->changeApproval($guest, ApprovalStatus::Approved);
    }

    public function approveEveryone(Event $event): int
    {
        return $this->guests->approveAllForEvent($event->id);
    }

    public function reject(Guest $guest): Guest
    {
        $this->ensureApprovalRequired($guest);

        return $this->changeApproval($guest, ApprovalStatus::Rejected);
    }

    public function waitlist(Guest $guest): Guest
    {
        $this->ensureApprovalRequired($guest);

        return $this->changeApproval($guest, ApprovalStatus::Waitlisted);
    }

    public function setRsvpStatus(string $guestId, GuestRsvpStatus $status): void
    {
        $this->guests->update($this->guests->findOrFail($guestId), ['rsvp_status' => $status]);
    }

    private function createGuest(string $eventId, GuestData $data, GuestSource $source, ?RegistrationDetailsData $details = null): Guest
    {
        return $this->transaction(function () use ($eventId, $data, $source, $details) {
            // Lock the event row so concurrent registrations cannot overbook.
            $event = $this->events->findAndLock($eventId);

            $this->ensureCapacity($event);
            $this->ensureUniqueContact($event->id, $data->email, $data->phone);

            /** @var Guest $guest */
            $guest = $this->persist(fn () => $this->guests->create([
                ...$data->toArray(),
                // The invited party counts until the guest answers.
                'additional_guests' => $data->invited_additional_guests ?? 0,
                'children' => $data->invited_children ?? 0,
                ...($details === null ? [] : $details->profile),
                'event_id' => $event->id,
                'source' => $source,
                // Only public requests wait for approval; guests the client adds are approved.
                'approval_status' => $source === GuestSource::PublicLink && $event->requiresApproval()
                    ? ApprovalStatus::Pending
                    : ApprovalStatus::Approved,
                'approval_status_changed_at' => now(),
                'rsvp_status' => GuestRsvpStatus::NotSent,
            ]));

            if ($details !== null) {
                $this->answers->sync($guest->id, $details->answers);
            }

            // The guest's personal RSVP link (domain/{slug}/{code}).
            $this->links->createForGuest($guest);

            return $guest;
        });
    }

    /** Lets other domains (seating) follow a guest's party size. */
    private function announcePartyChange(Guest $guest): void
    {
        if ($guest->wasChanged(['additional_guests', 'children'])) {
            GuestPartyChanged::dispatch($guest);
        }
    }

    /**
     * Keeps the guest's party in step with a changed invitation: before they
     * answer it is the invited party; after, their answer is capped by it.
     *
     * @return array<string, int>
     */
    private function invitedParty(Guest $guest, UpdateGuestData $data): array
    {
        $party = [];

        foreach (['invited_additional_guests' => 'additional_guests', 'invited_children' => 'children'] as $invited => $count) {
            if (! $data->has($invited)) {
                continue;
            }

            $allowance = $data->get($invited);

            if (! $guest->hasResponded()) {
                $party[$count] = $allowance ?? 0;
            } elseif ($allowance !== null) {
                $party[$count] = min($guest->{$count}, $allowance);
            }
        }

        return $party;
    }

    private function changeApproval(Guest $guest, ApprovalStatus $status): Guest
    {
        if ($guest->approval_status === $status) {
            return $guest;
        }

        return $this->transaction(function () use ($guest, $status) {
            // Moving a guest from rejected / waitlisted into a seat needs capacity.
            if ($status->countsTowardCapacity() && ! $guest->approval_status->countsTowardCapacity()) {
                $this->ensureCapacity($this->events->findAndLock($guest->event_id));
            }

            /** @var Guest */
            return $this->guests->update($guest, ['approval_status' => $status, 'approval_status_changed_at' => now()]);
        });
    }

    /** Waitlisting and rejecting only apply to events whose registrations need approval. */
    private function ensureApprovalRequired(Guest $guest): void
    {
        if (! $guest->event->requiresApproval()) {
            throw new ApprovalNotRequiredException;
        }
    }

    private function ensureCapacity(Event $event): void
    {
        if (! $event->hasUnlimitedCapacity() && $this->guests->countActive($event->id) >= $event->max_capacity) {
            throw new EventAtCapacityException;
        }
    }

    private function ensureUniqueContact(string $eventId, ?string $email, ?string $phone, ?string $exceptId = null): void
    {
        if ($email !== null && $this->guests->emailExists($eventId, $email, $exceptId)) {
            throw new GuestEmailConflictException;
        }

        if ($phone !== null && $this->guests->phoneExists($eventId, $phone, $exceptId)) {
            throw new GuestPhoneConflictException;
        }
    }

    /**
     * Maps a race on the partial unique indexes to the same domain errors
     * the pre-checks raise.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     */
    private function persist(callable $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException $e) {
            throw str_contains($e->getMessage(), 'guests_event_email_unique')
                ? new GuestEmailConflictException(previous: $e)
                : new GuestPhoneConflictException(previous: $e);
        }
    }
}
