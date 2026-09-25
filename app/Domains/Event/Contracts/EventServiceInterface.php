<?php

namespace App\Domains\Event\Contracts;

use App\Domains\Client\Models\Client;
use App\Domains\Event\DTOs\CreateEventData;
use App\Domains\Event\DTOs\UpdateEventData;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Models\Event;

interface EventServiceInterface
{
    public function create(Client $client, CreateEventData $data): Event;

    public function update(Event $event, UpdateEventData $data): Event;

    public function transition(Event $event, EventState $state): Event;

    /** Re-pin the event to the latest version of its template. */
    public function upgradeTemplate(Event $event): Event;

    public function openRegistration(Event $event): Event;

    public function closeRegistration(Event $event): Event;

    public function delete(Event $event): void;
}
