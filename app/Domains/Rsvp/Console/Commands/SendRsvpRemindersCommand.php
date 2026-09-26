<?php

namespace App\Domains\Rsvp\Console\Commands;

use App\Domains\Rsvp\Contracts\RsvpServiceInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rsvps:send-reminders')]
#[Description('Send the automatic RSVP reminders that are due (events with automatic reminders on)')]
class SendRsvpRemindersCommand extends Command
{
    public function handle(RsvpServiceInterface $rsvps): int
    {
        $count = $rsvps->sendDueReminders();

        $this->components->info("Sent {$count} RSVP reminder(s).");

        return self::SUCCESS;
    }
}
