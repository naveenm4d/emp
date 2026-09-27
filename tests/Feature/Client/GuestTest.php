<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\RegistrationQuestion;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Guest\Models\RegistrationAnswer;
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
    $this->post("/app/events/{$this->event->id}/guests", ['name' => 'John', 'phone' => '+15550100400', 'invitation_message' => 'Hi John: {{ rsvp.link }}'])
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

    $this->post("/app/events/{$this->event->id}/guests", ['name' => 'Ada', 'email' => 'ada@example.com'])->assertSessionHas('success');

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

it('loads the open guest\'s RSVP links, messages and answers into the details panel', function () {
    $guest = Guest::factory()->for($this->event)->create();
    $old = Rsvp::factory()->for($guest)->for($this->event)->create(['status' => 'expired', 'created_at' => now()->subDays(3)]);
    $new = Rsvp::factory()->for($guest)->for($this->event)->sent()->create();
    Notification::factory()->for($guest)->for($this->event)->create(['rsvp_id' => $new->id, 'kind' => 'rsvp_invitation', 'created_at' => now()->subHour()]);
    Notification::factory()->for($guest)->for($this->event)->create(['rsvp_id' => $new->id, 'kind' => 'rsvp_reminder']);
    Notification::factory()->for($this->event)->create();
    $second = RegistrationQuestion::factory()->for($this->event)->create(['label' => 'Second', 'sort_order' => 2]);
    $first = RegistrationQuestion::factory()->for($this->event)->create(['label' => 'First', 'sort_order' => 1]);
    RegistrationAnswer::factory()->for($guest)->create(['question_id' => $second->id]);
    RegistrationAnswer::factory()->for($guest)->create(['question_id' => $first->id]);

    $this->get("/app/events/{$this->event->id}/guests?guest={$guest->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('guestDetails.guest_id', $guest->id)
            ->where('guestDetails.rsvps.0.id', $new->id)
            ->where('guestDetails.rsvps.1.id', $old->id)
            ->has('guestDetails.messages', 2)
            ->where('guestDetails.messages.0.kind', 'rsvp_reminder')
            ->where('guestDetails.messages.1.kind', 'rsvp_invitation')
            ->where('guestDetails.answers.0.question', 'First')
            ->where('guestDetails.answers.1.question', 'Second'));
});

it('loads guest details on the RSVPs page too, and nothing without a guest', function () {
    $guest = Guest::factory()->for($this->event)->create();
    Rsvp::factory()->for($guest)->for($this->event)->sent()->create();

    $this->get("/app/events/{$this->event->id}/rsvps?guest={$guest->id}")
        ->assertInertia(fn (Assert $page) => $page->where('guestDetails.guest_id', $guest->id));

    $this->get("/app/events/{$this->event->id}/guests")
        ->assertInertia(fn (Assert $page) => $page->where('guestDetails', null));
});

it('only loads details of the event\'s own guests', function () {
    $other = Guest::factory()->create();

    $this->get("/app/events/{$this->event->id}/guests?guest={$other->id}")->assertNotFound();
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

it('needs an email or a phone to add a guest', function () {
    $this->post("/app/events/{$this->event->id}/guests", ['name' => 'Ada'])
        ->assertSessionHasErrors(['email' => 'Add a phone number or an email.']);

    expect(Guest::count())->toBe(0);
});

it('refuses an update that removes the guest\'s only contact', function () {
    $guest = Guest::factory()->for($this->event)->withoutPhone()->create(['email' => 'ada@example.com']);

    $this->patch("/app/guests/{$guest->id}", ['email' => ''])
        ->assertSessionHasErrors(['email' => 'Add a phone number or an email.']);

    expect($guest->fresh()->email)->toBe('ada@example.com');
});

it('adds a guest invited with plus-ones and children, counted in the headcount', function () {
    $this->post("/app/events/{$this->event->id}/guests", [
        'name' => 'John',
        'phone' => '+15550100200',
        'invited_additional_guests' => 1,
        'invited_children' => 2,
    ])->assertSessionHas('success');

    expect(Guest::sole())
        ->invited_additional_guests->toBe(1)
        ->invited_children->toBe(2)
        ->additional_guests->toBe(1)
        ->children->toBe(2);

    $this->get("/app/events/{$this->event->id}/guests")
        ->assertInertia(fn (Assert $page) => $page->where('summary.headcount_total', 4));
});

it('caps an answered party when the client lowers the invitation', function () {
    $guest = Guest::factory()->for($this->event)->create([
        'rsvp_status' => 'confirmed',
        'invited_additional_guests' => 2,
        'additional_guests' => 2,
    ]);

    $this->patch("/app/guests/{$guest->id}", ['invited_additional_guests' => 1])->assertSessionHas('success');

    expect($guest->fresh())
        ->invited_additional_guests->toBe(1)
        ->additional_guests->toBe(1);
});
