<?php

namespace App\Domains\Rsvp\Contracts;

use App\Domains\Guest\DTOs\RegistrationDetailsData;
use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Enums\RsvpReminderType;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Models\Rsvp;

interface RsvpServiceInterface
{
    public function create(Guest $guest): Rsvp;

    public function send(Rsvp $rsvp): Rsvp;

    public function resend(Rsvp $rsvp): Rsvp;

    public function expire(Rsvp $rsvp): Rsvp;

    /**
     * Sends a reminder for a sent, unanswered link. Automatic reminders pass
     * the type(s) they fulfil, so each goes out only once per link.
     */
    public function remind(Rsvp $rsvp, RsvpReminderType ...$automatic): Rsvp;

    /** Sends the automatic reminders that are due; returns how many were sent. */
    public function sendDueReminders(): int;

    /**
     * Records the guest's answer (accepted, declined or maybe), the details
     * they gave, and their note to the host when declining. A second answer only replaces the first while the event
     * allows changes; otherwise the current state is returned.
     */
    public function respond(string $token, RsvpStatus $response, ?RegistrationDetailsData $details = null, ?string $note = null): Rsvp;
}
