<?php

namespace App\Domains\Rsvp\Contracts;

use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Enums\RsvpReminderType;
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

    public function accept(string $token): Rsvp;

    public function decline(string $token): Rsvp;
}
