<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Models\Event;
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

it('rejects a duplicate slug', function () {
    Event::factory()->create(['slug' => 'taken']);

    $this->post('/app/events', ['title' => 'Mine', 'slug' => 'taken', 'template_id' => $this->template->id])->assertSessionHasErrors('slug');
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

it('shows the event with guest and invitation summaries', function () {
    $event = Event::factory()->for($this->client)->create();

    $this->get("/app/events/{$event->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/events/show')
            ->where('event.data.id', $event->id)
            ->where('guestSummary.total', 0)
            ->where('invitationSummary.total', 0));
});
