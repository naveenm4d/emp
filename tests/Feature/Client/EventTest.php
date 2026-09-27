<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Models\Notification;
use App\Domains\Template\Models\Template;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->actingAs($this->client, 'client');
    $this->template = Template::factory()->published()->create();
});

it('lists only the client\'s own events', function () {
    Event::factory()->for($this->client)->count(2)->create();
    Event::factory()->create();

    $this->get('/app/events')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/events/index')
            ->has('events.data', 2));
});

it('shows each event\'s replies, waiting parties and failed messages in the list', function () {
    $event = Event::factory()->for($this->client)->create();
    Guest::factory()->for($event)->create(['rsvp_status' => GuestRsvpStatus::Confirmed, 'additional_guests' => 1, 'children' => 1]);
    Guest::factory()->for($event)->create(['rsvp_status' => GuestRsvpStatus::Declined]);
    $waiting = Guest::factory()->for($event)->count(2)->create(['rsvp_status' => GuestRsvpStatus::Pending]);
    Guest::factory()->for($event)->status(ApprovalStatus::Pending)->create();
    Notification::factory()->for($waiting[0])->failed()->create(['event_id' => $event->id]);
    Notification::factory()->for($waiting[1])->failed()->create(['event_id' => $event->id, 'created_at' => now()->subHour()]);
    Notification::factory()->for($waiting[1])->create(['event_id' => $event->id, 'status' => NotificationStatus::Delivered]);

    $response = $this->get('/app/events');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('events.data.0.guests_count', 5)
        ->where('events.data.0.attending_count', 1)
        ->where('events.data.0.declined_count', 1)
        ->where('events.data.0.waiting_count', 2)
        ->where('events.data.0.to_approve_count', 1)
        ->where('events.data.0.headcount', 3)
        ->where('events.data.0.failed_messages_count', 1));
});

it('lists upcoming events soonest first by default and past events on request', function () {
    Event::factory()->for($this->client)->create(['title' => 'Later', 'event_date' => now()->addMonth()->toDateString()]);
    Event::factory()->for($this->client)->create(['title' => 'Sooner', 'event_date' => now()->addWeek()->toDateString()]);
    Event::factory()->for($this->client)->create(['title' => 'Held', 'event_date' => now()->subWeek()->toDateString()]);

    $this->get('/app/events')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.period', 'upcoming')
            ->where('events.data.0.title', 'Sooner')
            ->where('events.data.1.title', 'Later')
            ->has('events.data', 2));

    $this->get('/app/events?period=past')
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.data.0.title', 'Held')
            ->has('events.data', 1));
});

it('totals upcoming events, drafts and parties waiting on a reply', function () {
    $upcoming = Event::factory()->for($this->client)->published()->create();
    Guest::factory()->for($upcoming)->count(3)->create(['rsvp_status' => GuestRsvpStatus::Pending]);
    Event::factory()->for($this->client)->create();
    $held = Event::factory()->for($this->client)->published()->create(['event_date' => now()->subWeek()->toDateString()]);
    Guest::factory()->for($held)->create(['rsvp_status' => GuestRsvpStatus::Pending]);

    $response = $this->get('/app/events');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('totals', ['upcoming' => 2, 'drafts' => 1, 'waiting' => 3]));
});

it('creates an event as a draft with a normalised slug', function () {
    $this->post('/app/events', ['title' => 'Summer Gala', 'slug' => ' Summer GALA 2026 ', 'max_capacity' => 50, 'template_id' => $this->template->id])
        ->assertRedirect();

    $event = Event::sole();
    expect($event->state)->toBe(EventState::Draft)
        ->and($event->slug)->toBe('summer-gala-2026')
        ->and($event->client_id)->toBe($this->client->id)
        ->and($event->registration_open)->toBeFalse();
});

it('derives the slug from the title when omitted', function () {
    $this->post('/app/events', ['title' => 'Founders Dinner', 'template_id' => $this->template->id])->assertRedirect();

    expect(Event::sole()->slug)->toBe('founders-dinner');
});

it('stores the chosen event type and returns its label', function () {
    $this->post('/app/events', ['title' => 'Shower', 'template_id' => $this->template->id, 'event_type' => 'baby_shower'])
        ->assertRedirect();

    $event = Event::sole();
    $this->get("/app/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.data.event_type', 'baby_shower')
            ->where('event.data.event_type_label', 'Baby Shower'));
});

it('rejects an event type outside the list', function () {
    $this->post('/app/events', ['title' => 'X', 'template_id' => $this->template->id, 'event_type' => 'rodeo'])
        ->assertSessionHasErrors(['event_type' => 'The selected event type is invalid.']);
});

it('only accepts an event date of today or later when creating', function () {
    $payload = ['title' => 'Gala', 'template_id' => $this->template->id];

    $this->post('/app/events', [...$payload, 'event_date' => now()->subDay()->toDateString()])
        ->assertSessionHasErrors(['event_date' => 'The event date must be today or later.']);

    $this->post('/app/events', [...$payload, 'event_date' => now()->toDateString()])
        ->assertSessionHasNoErrors();
});

it('still lets a past-dated event be edited', function () {
    $event = Event::factory()->for($this->client)->create([
        'template_version_id' => $this->template->latest_version_id,
        'event_date' => now()->subMonth()->toDateString(),
    ]);

    $this->patch("/app/events/{$event->id}", ['title' => 'Renamed', 'event_date' => $event->event_date->toDateString()])
        ->assertSessionHasNoErrors();
});

it('requires the end time to be after the start time', function (array $times, string $field, string $message) {
    $this->post('/app/events', ['title' => 'Gala', 'template_id' => $this->template->id, ...$times])
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'before start' => [['start_time' => '18:00', 'end_time' => '17:00'], 'end_time', 'The end time must be after the start time.'],
    'same as start' => [['start_time' => '18:00', 'end_time' => '18:00'], 'end_time', 'The end time must be after the start time.'],
    'no start' => [['end_time' => '18:00'], 'start_time', 'Set a start time when the event has an end time.'],
]);

it('edits details on the Settings tab and returns there after saving', function () {
    $event = Event::factory()->for($this->client)->create(['template_version_id' => $this->template->latest_version_id]);

    $this->get("/app/events/{$event->id}/edit")
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/events/edit')
            ->where('event.data.id', $event->id)
            ->has('eventTypes')
            ->has('registrationTypes'));

    $this->patch("/app/events/{$event->id}", ['title' => 'Renamed'])
        ->assertRedirect("/app/events/{$event->id}/edit");
});

it('allows the same slug on different events, since the link code identifies the event', function () {
    Event::factory()->create(['slug' => 'john-and-amy']);

    $this->post('/app/events', ['title' => 'Ours', 'slug' => 'john-and-amy', 'template_id' => $this->template->id])
        ->assertSessionHasNoErrors();

    $event = Event::where('title', 'Ours')->sole();
    expect($event->slug)->toBe('john-and-amy')
        ->and($event->publicLink->code)->toMatch('/^[a-z2-9]{8}$/');
});

it('rejects slugs the app uses itself', function (string $slug) {
    $this->post('/app/events', ['title' => 'Mine', 'slug' => $slug, 'template_id' => $this->template->id])
        ->assertSessionHasErrors(['slug' => 'This URL is reserved; choose another slug.']);
})->with(['admin', 'app', 'webhooks']);

it('only soft-deletes events', function () {
    $event = Event::factory()->for($this->client)->create();

    $this->delete("/app/events/{$event->id}")->assertRedirect();

    $this->assertSoftDeleted($event);
});
it('forbids access to another client\'s event', function (string $method, string $suffix) {
    $event = Event::factory()->create();

    $this->{$method}("/app/events/{$event->id}{$suffix}")->assertForbidden();
})->with([
    'show' => ['get', ''],
    'edit' => ['get', '/edit'],
    'update' => ['patch', ''],
    'delete' => ['delete', ''],
    'guests' => ['get', '/guests'],
    'open registration' => ['post', '/registration/open'],
]);

it('keeps the slug when only the title changes', function () {
    $event = Event::factory()->for($this->client)->create(['slug' => 'original']);

    $this->patch("/app/events/{$event->id}", ['title' => 'Renamed'])->assertRedirect();

    expect($event->fresh())->title->toBe('Renamed')->slug->toBe('original');
});

it('saves and clears the event\'s invitation message', function () {
    $event = Event::factory()->for($this->client)->create();

    $this->patch("/app/events/{$event->id}", ['invitation_message' => 'Hi {{ guest.name }}, join us: {{ rsvp.link }}'])
        ->assertSessionHasNoErrors();
    expect($event->fresh()->invitation_message)->toBe('Hi {{ guest.name }}, join us: {{ rsvp.link }}');

    $this->patch("/app/events/{$event->id}", ['invitation_message' => ''])->assertSessionHasNoErrors();
    expect($event->fresh()->invitation_message)->toBeNull();
});

it('rejects an invitation message over 1000 characters', function () {
    $event = Event::factory()->for($this->client)->create();

    $this->patch("/app/events/{$event->id}", ['invitation_message' => str_repeat('a', 1001)])
        ->assertSessionHasErrors('invitation_message');
});

it('creates guest-list-only events unless another registration type is chosen', function () {
    $this->post('/app/events', ['title' => 'Private', 'template_id' => $this->template->id])->assertRedirect();
    $this->post('/app/events', ['title' => 'Public', 'template_id' => $this->template->id, 'registration_type' => 'approval_required'])->assertRedirect();

    expect(Event::where('title', 'Private')->sole()->registration_type)->toBe(RegistrationType::GuestListOnly)
        ->and(Event::where('title', 'Public')->sole()->registration_type)->toBe(RegistrationType::ApprovalRequired);

    $this->post('/app/events', ['title' => 'Bad', 'template_id' => $this->template->id, 'registration_type' => 'invite_only'])
        ->assertSessionHasErrors('registration_type');
});

it('has no public registration to open for guest-list-only events', function () {
    $event = Event::factory()->for($this->client)->create(['registration_type' => RegistrationType::GuestListOnly]);

    $this->post("/app/events/{$event->id}/registration/open")
        ->assertSessionHas('error', 'Guest-list-only events have no public registration.');
    expect($event->fresh()->registration_open)->toBeFalse();
});

it('closes registration when an event switches to guest list only', function () {
    $event = Event::factory()->for($this->client)->openForRegistration()->create();

    $this->patch("/app/events/{$event->id}", ['registration_type' => 'guest_list_only'])->assertSessionHasNoErrors();

    expect($event->fresh())
        ->registration_type->toBe(RegistrationType::GuestListOnly)
        ->registration_open->toBeFalse();
});

it('saves the reminder message and automatic reminder settings', function () {
    $event = Event::factory()->for($this->client)->create();

    $this->patch("/app/events/{$event->id}", [
        'reminder_message' => 'Still coming, {{ guest.name }}?',
        'auto_reminders' => true,
        'remind_after_days' => 4,
        'remind_before_days' => 1,
    ])->assertSessionHasNoErrors();

    expect($event->fresh())
        ->reminder_message->toBe('Still coming, {{ guest.name }}?')
        ->auto_reminders->toBeTrue()
        ->remind_after_days->toBe(4)
        ->remind_before_days->toBe(1);

    $this->patch("/app/events/{$event->id}", ['remind_after_days' => 0, 'remind_before_days' => 61])
        ->assertSessionHasErrors(['remind_after_days', 'remind_before_days']);
});

it('publishes a draft', function () {
    $event = Event::factory()->for($this->client)->create();

    $this->patch("/app/events/{$event->id}/state", ['state' => 'published'])->assertRedirect();

    expect($event->fresh()->state)->toBe(EventState::Published);
});

it('refuses an illegal state transition with a flash error', function () {
    $event = Event::factory()->for($this->client)->cancelled()->create();

    $this->from("/app/events/{$event->id}")
        ->patch("/app/events/{$event->id}/state", ['state' => 'published'])
        ->assertRedirect("/app/events/{$event->id}")
        ->assertSessionHas('error', 'An event cannot move from cancelled to published.');

    expect($event->fresh()->state)->toBe(EventState::Cancelled);
});

it('closes registration when an event is cancelled', function () {
    $event = Event::factory()->for($this->client)->openForRegistration()->create();

    $this->patch("/app/events/{$event->id}/state", ['state' => 'cancelled']);

    expect($event->fresh())->state->toBe(EventState::Cancelled)->registration_open->toBeFalse();
});

it('does not allow editing a cancelled event', function () {
    $event = Event::factory()->for($this->client)->cancelled()->create();

    $this->patch("/app/events/{$event->id}", ['title' => 'Nope'])->assertSessionHas('error');

    expect($event->fresh()->title)->not->toBe('Nope');
});

it('shows the event with guest and RSVP summaries', function () {
    $event = Event::factory()->for($this->client)->create();

    $this->get("/app/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/events/show')
            ->where('event.data.id', $event->id)
            ->where('guestSummary.total', 0)
            ->where('rsvpSummary.total', 0));
});

it('shows who is waiting on a reply, failed messages and seated people on the overview', function () {
    $event = Event::factory()->for($this->client)->create();
    $waiting = Guest::factory()->for($event)->create(['name' => 'Ann', 'rsvp_status' => GuestRsvpStatus::Pending]);
    Guest::factory()->for($event)->status(ApprovalStatus::Pending)->create(['rsvp_status' => GuestRsvpStatus::Pending]);
    Guest::factory()->for($event)->create(['rsvp_status' => GuestRsvpStatus::Confirmed, 'additional_guests' => 2, 'children' => 1]);
    Notification::factory()->for($waiting)->failed()->create(['event_id' => $event->id]);

    $response = $this->get("/app/events/{$event->id}");

    $response->assertInertia(fn (Assert $page) => $page
        ->has('waiting.data', 1)
        ->where('waiting.data.0.name', 'Ann')
        ->where('messageIssues', ['failed' => 1, 'queued' => 0])
        ->where('seated', 0)
        ->where('guestSummary.rsvp_pending', 2)
        ->where('guestSummary.confirmed_additional', 2)
        ->where('guestSummary.confirmed_children', 1));
});
