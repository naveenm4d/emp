<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Models\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->event = Event::factory()->for($this->client)->create();
    $this->actingAs($this->client->owner, 'client');

    $guest = Guest::factory()->for($this->event)->create();
    Notification::factory()->for($guest)->count(2)->create(['status' => NotificationStatus::Read]);
    Notification::factory()->for($guest)->create(['status' => NotificationStatus::Delivered]);
    Notification::factory()->for($guest)->failed()->create();
    // Another event's message is never counted.
    Notification::factory()->failed()->create();
});

it('counts the event\'s messages by status for the filter', function () {
    $this->get("/app/events/{$this->event->id}/notifications")->assertInertia(fn (Assert $page) => $page
        ->has('notifications.data', 4)
        ->where('status', null)
        ->where('statusCounts', [
            'all' => 4,
            'pending' => 0,
            'sent' => 0,
            'delivered' => 1,
            'read' => 2,
            'failed' => 1,
        ]));
});

it('lists only the messages with the chosen status', function () {
    $this->get("/app/events/{$this->event->id}/notifications?status=read")->assertInertia(fn (Assert $page) => $page
        ->has('notifications.data', 2)
        ->where('notifications.data.0.status', 'read')
        ->where('status', 'read')
        ->where('statusCounts.all', 4));
});

it('shows every message for an unknown status', function () {
    $this->get("/app/events/{$this->event->id}/notifications?status=lost")->assertInertia(fn (Assert $page) => $page
        ->has('notifications.data', 4)
        ->where('status', null));
});
