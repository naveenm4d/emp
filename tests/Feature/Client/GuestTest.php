<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Models\Notification;
use App\Domains\Rsvp\Models\Rsvp;
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

    $guest = Guest::sole();
    expect($guest)->source->value->toBe('manual')->approval_status->toBe(ApprovalStatus::Approved)
        ->and($guest->link->code)->toMatch('/^[a-z2-9]{8}$/')
        ->and($guest->link->event_id)->toBe($this->event->id);
});

it('saves a guest\'s custom invitation message on add and update', function () {
    $this->post("/app/events/{$this->event->id}/guests", ['name' => 'John', 'invitation_message' => 'Hi John: {{ rsvp.link }}'])
        ->assertSessionHas('success');

    $guest = Guest::sole();
    expect($guest->invitation_message)->toBe('Hi John: {{ rsvp.link }}');

    $this->patch("/app/guests/{$guest->id}", ['invitation_message' => ''])->assertSessionHas('success');
    expect($guest->fresh()->invitation_message)->toBeNull();
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
    $this->event->update(['registration_type' => 'approval_required']);
    $guest = Guest::factory()->for($this->event)->status(ApprovalStatus::Pending)->create();

    $this->post("/app/guests/{$guest->id}/reject");
    expect($guest->fresh()->approval_status)->toBe(ApprovalStatus::Rejected);

    $this->post("/app/guests/{$guest->id}/waitlist");
    expect($guest->fresh()->approval_status)->toBe(ApprovalStatus::Waitlisted);

    $this->post("/app/guests/{$guest->id}/approve");
    expect($guest->fresh()->approval_status)->toBe(ApprovalStatus::Approved);
});

it('approves guests the client adds, even when registrations need approval', function () {
    $this->event->update(['registration_type' => 'approval_required']);

    $this->post("/app/events/{$this->event->id}/guests", ['name' => 'Ada'])->assertSessionHas('success');

    expect(Guest::sole())
        ->approval_status->toBe(ApprovalStatus::Approved)
        ->approval_status_changed_at->not->toBeNull();
});

it('only waitlists or rejects guests of events that need approval', function (string $action) {
    $guest = Guest::factory()->for($this->event)->status(ApprovalStatus::Pending)->create();

    $this->post("/app/guests/{$guest->id}/{$action}")
        ->assertSessionHas('error', 'This event does not use guest approval.');
    expect($guest->fresh()->approval_status)->toBe(ApprovalStatus::Pending);
})->with(['waitlist', 'reject']);

it('records when the approval status changed', function () {
    $this->event->update(['registration_type' => 'approval_required']);
    $guest = Guest::factory()->for($this->event)->status(ApprovalStatus::Pending)->create(['approval_status_changed_at' => now()->subWeek()]);

    $this->travelTo(now()->addMinute());
    $this->post("/app/guests/{$guest->id}/waitlist");

    expect($guest->fresh()->approval_status_changed_at->isSameMinute(now()))->toBeTrue();
});

it('does not approve a waitlisted guest past capacity', function () {
    $this->event->update(['max_capacity' => 1, 'registration_type' => 'approval_required']);
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

it('lists guests with their latest RSVP link and summary', function () {
    $guest = Guest::factory()->for($this->event)->create(['name' => 'Ada']);
    Rsvp::factory()->for($guest)->for($this->event)->sent()->create();
    Guest::factory()->for($this->event)->status(ApprovalStatus::Pending)->create(['name' => 'Ben']);

    $this->get("/app/events/{$this->event->id}/guests")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/guests/index')
            ->has('guests.data', 2)
            ->where('guests.data.0.latest_rsvp.status', 'sent')
            ->where('summary.total', 2)
            ->where('summary.pending', 1));
});

it('lists guests alphabetically and keeps the order after an update', function () {
    $zoe = Guest::factory()->for($this->event)->create(['name' => 'Zoe']);
    Guest::factory()->for($this->event)->create(['name' => 'ada']);
    Guest::factory()->for($this->event)->create(['name' => 'Ben']);

    $this->patch("/app/guests/{$zoe->id}", ['name' => 'Zoe', 'notes' => 'Vegetarian'])->assertSessionHas('success');

    $this->get("/app/events/{$this->event->id}/guests")
        ->assertInertia(fn (Assert $page) => $page
            ->where('guests.data.0.name', 'ada')
            ->where('guests.data.1.name', 'Ben')
            ->where('guests.data.2.name', 'Zoe'));
});

it('returns the guest\'s RSVP links and messages for the details timeline', function () {
    $guest = Guest::factory()->for($this->event)->create();
    $old = Rsvp::factory()->for($guest)->for($this->event)->create(['status' => 'expired', 'created_at' => now()->subDays(3)]);
    $new = Rsvp::factory()->for($guest)->for($this->event)->sent()->create();
    Notification::factory()->for($guest)->for($this->event)->create(['rsvp_id' => $new->id, 'kind' => 'rsvp_invitation', 'created_at' => now()->subHour()]);
    Notification::factory()->for($guest)->for($this->event)->create(['rsvp_id' => $new->id, 'kind' => 'rsvp_reminder']);
    Notification::factory()->for($this->event)->create();

    $this->getJson("/app/guests/{$guest->id}/details")
        ->assertOk()
        ->assertJsonPath('rsvps.0.id', $new->id)
        ->assertJsonPath('rsvps.1.id', $old->id)
        ->assertJsonCount(2, 'messages')
        ->assertJsonPath('messages.0.kind', 'rsvp_reminder')
        ->assertJsonPath('messages.1.kind', 'rsvp_invitation');
});

it('forbids another client\'s guest details', function () {
    $this->getJson('/app/guests/'.Guest::factory()->create()->id.'/details')->assertForbidden();
});

it('has no approval on guest-list-only events', function () {
    $guest = Guest::factory()->for($this->event)->status(ApprovalStatus::Pending)->create();

    $this->post("/app/guests/{$guest->id}/approve")
        ->assertSessionHas('error', 'This event does not use guest approval.');
});

it('approves everyone on the list when an event switches to guest list only', function () {
    $this->event->update(['registration_type' => 'approval_required']);
    $pending = Guest::factory()->for($this->event)->status(ApprovalStatus::Pending)->create();
    $waitlisted = Guest::factory()->for($this->event)->status(ApprovalStatus::Waitlisted)->create();
    $other = Guest::factory()->status(ApprovalStatus::Pending)->create();

    $this->patch("/app/events/{$this->event->id}", ['registration_type' => 'guest_list_only'])->assertSessionHasNoErrors();

    expect($pending->fresh()->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($waitlisted->fresh()->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($other->fresh()->approval_status)->toBe(ApprovalStatus::Pending);
});
