<?php

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Models\Guest;
use Inertia\Testing\AssertableInertia as Assert;

it('shows a published event without internal fields', function () {
    $event = Event::factory()->openForRegistration()->create(['slug' => 'summer-gala']);

    $this->get('/e/SUMMER-GALA')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('site/events/show')
            ->where('event.id', $event->id)
            ->where('event.registration_open', true)
            ->missing('event.client_id')
            ->missing('event.state')
            ->where('actions.register', route('site.events.register', 'summer-gala')));
});

it('hides draft and cancelled events', function (string $state) {
    Event::factory()->create(['slug' => 'secret', 'state' => $state]);

    $this->get('/e/secret')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('site/errors/not-found'));
})->with(['draft', 'cancelled']);

it('registers a guest through the public page', function () {
    $event = Event::factory()->openForRegistration()->create();

    $this->from("/e/{$event->slug}")
        ->post("/e/{$event->slug}/register", [
            'name' => 'Ada Lovelace',
            'email' => ' ADA@example.com ',
            'phone' => '+1 (555) 010-0200',
        ])
        ->assertRedirect("/e/{$event->slug}")
        ->assertSessionHas('success', "You're registered, Ada Lovelace. See you there!");

    $guest = Guest::sole();
    expect($guest->email)->toBe('ada@example.com')
        ->and($guest->phone)->toBe('+15550100200')
        ->and($guest->source->value)->toBe('public_link')
        ->and($guest->approval_status)->toBe(ApprovalStatus::Approved);
});

it('puts guests in pending when the event requires approval', function () {
    $event = Event::factory()->openForRegistration()->requiresApproval()->create();

    $this->post("/e/{$event->slug}/register", ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSessionHas('success', "Thanks, Ada! Your registration is awaiting the host's approval.");

    expect(Guest::sole()->approval_status)->toBe(ApprovalStatus::Pending);
});

it('rejects registration when registration is closed', function () {
    $event = Event::factory()->published()->create(['registration_open' => false]);

    $this->post("/e/{$event->slug}/register", ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSessionHas('error');

    expect(Guest::count())->toBe(0);
});

it('enforces capacity', function () {
    $event = Event::factory()->openForRegistration()->capacity(1)->create();
    Guest::factory()->for($event)->create();

    $this->post("/e/{$event->slug}/register", ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSessionHas('error');

    expect(Guest::count())->toBe(1);
});

it('does not count rejected or waitlisted guests toward capacity', function () {
    $event = Event::factory()->openForRegistration()->capacity(1)->create();
    Guest::factory()->for($event)->status(ApprovalStatus::Rejected)->create();
    Guest::factory()->for($event)->status(ApprovalStatus::Waitlisted)->create();

    $this->post("/e/{$event->slug}/register", ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSessionHas('success');
});

it('rejects duplicate email and phone within an event', function (array $payload) {
    $event = Event::factory()->openForRegistration()->create();
    Guest::factory()->for($event)->create(['email' => 'ada@example.com', 'phone' => '+15550100200']);

    $this->post("/e/{$event->slug}/register", ['name' => 'Ada', ...$payload])
        ->assertSessionHas('error');

    expect(Guest::count())->toBe(1);
})->with([
    'email (case-insensitive)' => [['email' => 'ADA@example.com']],
    'phone (normalised)' => [['phone' => '+1 555 010 0200']],
]);

it('validates the registration form', function () {
    $event = Event::factory()->openForRegistration()->create();

    $this->post("/e/{$event->slug}/register", [])
        ->assertSessionHasErrors(['name', 'email']);
});
