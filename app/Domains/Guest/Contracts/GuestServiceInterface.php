<?php

namespace App\Domains\Guest\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\DTOs\GuestData;
use App\Domains\Guest\DTOs\UpdateGuestData;
use App\Domains\Guest\Enums\RsvpStatus;
use App\Domains\Guest\Models\Guest;

interface GuestServiceInterface
{
    /** Client adds a guest from the dashboard. */
    public function add(Event $event, GuestData $data): Guest;

    /** A guest registers through the public event page. */
    public function registerPublic(string $slug, GuestData $data): Guest;

    public function update(Guest $guest, UpdateGuestData $data): Guest;

    public function delete(Guest $guest): void;

    public function approve(Guest $guest): Guest;

    public function reject(Guest $guest): Guest;

    public function waitlist(Guest $guest): Guest;

    public function setRsvpStatus(string $guestId, RsvpStatus $status): void;
}
