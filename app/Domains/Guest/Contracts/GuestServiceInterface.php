<?php

namespace App\Domains\Guest\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\DTOs\GuestData;
use App\Domains\Guest\DTOs\RegistrationDetailsData;
use App\Domains\Guest\DTOs\UpdateGuestData;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Models\Guest;

interface GuestServiceInterface
{
    /** Client adds a guest from the dashboard. */
    public function add(Event $event, GuestData $data): Guest;

    /** Self-registration through the event's public link (published, public registration open), with the details the event asks for. */
    public function registerPublic(Event $event, GuestData $data, ?RegistrationDetailsData $details = null): Guest;

    /** Stores the details a guest gave when RSVPing (built-in fields and custom answers). */
    public function saveRegistrationDetails(string $guestId, RegistrationDetailsData $details): Guest;

    public function update(Guest $guest, UpdateGuestData $data): Guest;

    public function delete(Guest $guest): void;

    public function approve(Guest $guest): Guest;

    /** Approves every guest of the event who is not approved yet (guest-list-only events have no approval). */
    public function approveEveryone(Event $event): int;

    public function reject(Guest $guest): Guest;

    public function waitlist(Guest $guest): Guest;

    public function setRsvpStatus(string $guestId, GuestRsvpStatus $status): void;
}
