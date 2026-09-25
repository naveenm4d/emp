<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Invitation\Models\Invitation;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->event = Event::factory()->for($this->client)->create();
    $this->actingAs($this->client, 'client');
});

it('adds a guest manually', function () {
    $this->post("/app/events/{$this->event->id}/guests", ['name' => 'Ada', 'phone' => '+15550100200'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Guest::sole())->source->value->toBe('manual')->approval_status->toBe(ApprovalStatus::Approved);
});

it('detects duplicates on update too', function () {
    Guest::factory()->for($this->event)->create(['phone' => '+15550100200']);
    $other = Guest::factory()->for($this->event)->create(['phone' => '+15550100300']);

    $this->patch("/app/guests/{$other->id}", ['phone' => '+1 555 010 0200'])
        ->assertSessionHas('error', 'A guest with this phone number is already registered for this event.');
});

it('allows the same contact in different events', function () {
    Guest::factory()->create(['email' => 'ada@example.com']);

    $this->post("/app/events/{$this->event->id}/guests", ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSessionHas('success');
});

it('approves, rejects and waitlists', function () {
    $guest = Guest::factory()->for($this->event)->status(ApprovalStatus::Pending)->create();

    $this->post("/app/guests/{$guest->id}/reject");
    expect($guest->fresh()->approval_status)->toBe(ApprovalStatus::Rejected);

    $this->post("/app/guests/{$guest->id}/waitlist");
    expect($guest->fresh()->approval_status)->toBe(ApprovalStatus::Waitlisted);

    $this->post("/app/guests/{$guest->id}/approve");
    expect($guest->fresh()->approval_status)->toBe(ApprovalStatus::Approved);
});

it('does not approve a waitlisted guest past capacity', function () {
    $this->event->update(['max_capacity' => 1]);
    Guest::factory()->for($this->event)->create();
    $waitlisted = Guest::factory()->for($this->event)->status(ApprovalStatus::Waitlisted)->create();

    $this->post("/app/guests/{$waitlisted->id}/approve")->assertSessionHas('error', 'This event has reached its capacity.');

    expect($waitlisted->fresh()->approval_status)->toBe(ApprovalStatus::Waitlisted);
});

it('forbids managing another client\'s guests', function () {
    $guest = Guest::factory()->create();

    $this->post("/app/guests/{$guest->id}/approve")->assertForbidden();
    $this->delete("/app/guests/{$guest->id}")->assertForbidden();
});

it('lists guests with their latest invitation and summary', function () {
    $guest = Guest::factory()->for($this->event)->create();
    Invitation::factory()->for($guest)->for($this->event)->sent()->create();
    Guest::factory()->for($this->event)->status(ApprovalStatus::Pending)->create();

    $this->get("/app/events/{$this->event->id}/guests")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/guests/index')
            ->has('guests.data', 2)
            ->where('guests.data.0.latest_invitation.status', 'sent')
            ->where('summary.total', 2)
            ->where('summary.pending', 1));
});
