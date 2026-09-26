<?php

namespace App\Domains\Event\Contracts;

use App\Domains\Event\DTOs\RegistrationSettingsData;
use App\Domains\Event\Models\Event;

/**
 * What an event asks guests when they register or RSVP.
 */
interface RegistrationSettingsServiceInterface
{
    /** Saves the built-in fields and lock time, and syncs the custom questions (missing ones are deleted with their answers). */
    public function update(Event $event, RegistrationSettingsData $data): Event;
}
