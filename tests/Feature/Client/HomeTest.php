<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Models\Guest;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the client\'s upcoming events soonest first with their totals', function () {
    $client = Client::factory()->create();
    $next = Event::factory()->for($client)->published()->create(['title' => 'Next', 'event_date' => now()->addDays(3)->toDateString()]);
    Guest::factory()->for($next)->count(2)->create(['rsvp_status' => GuestRsvpStatus::Pending]);
    Event::factory()->for($client)->create(['title' => 'Later', 'event_date' => now()->addMonths(2)->toDateString()]);
    Event::factory()->for($client)->create(['title' => 'Held', 'event_date' => now()->subDay()->toDateString()]);
    Event::factory()->create(['event_date' => now()->addDay()->toDateString()]);

    $response = $this->actingAs($client, 'client')->get('/app');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('client/home')
        ->has('events.data', 2)
        ->where('events.data.0.title', 'Next')
        ->where('events.data.0.waiting_count', 2)
        ->where('events.data.1.title', 'Later')
        ->where('totals', ['upcoming' => 2, 'drafts' => 2, 'waiting' => 2]));
});

it('sends guests to the login page', function () {
    $this->get('/app')->assertRedirect('/app/login');
});

it('never picks a cancelled event as next up', function () {
    $client = Client::factory()->create();
    Event::factory()->for($client)->cancelled()->create(['title' => 'Called off', 'event_date' => now()->addDay()->toDateString()]);
    Event::factory()->for($client)->published()->create(['title' => 'On', 'event_date' => now()->addWeek()->toDateString()]);

    $this->actingAs($client, 'client')->get('/app')->assertInertia(fn (Assert $page) => $page
        ->where('next.data.title', 'On')
        ->has('events.data', 2));
});

it('never picks a draft as next up', function () {
    $client = Client::factory()->create();
    Event::factory()->for($client)->create(['title' => 'Draft', 'event_date' => now()->addDay()->toDateString()]);
    Event::factory()->for($client)->published()->create(['title' => 'Live', 'event_date' => now()->addWeek()->toDateString()]);

    $this->actingAs($client, 'client')->get('/app')->assertInertia(fn (Assert $page) => $page
        ->where('next.data.title', 'Live')
        ->has('next.data.template.thumbnail_url'));
});

it('has no next up without a published upcoming event', function () {
    $client = Client::factory()->create();
    Event::factory()->for($client)->cancelled()->create(['event_date' => now()->addDay()->toDateString()]);
    Event::factory()->for($client)->create(['event_date' => now()->addDays(2)->toDateString()]);

    $this->actingAs($client, 'client')->get('/app')->assertInertia(fn (Assert $page) => $page
        ->where('next', null)
        ->has('events.data', 2));
});
