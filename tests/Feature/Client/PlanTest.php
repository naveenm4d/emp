<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Models\Notification;
use App\Domains\Rsvp\Models\Rsvp;
use App\Domains\Seating\Models\EventTable;
use App\Domains\Seating\Models\VenueElement;
use App\Domains\Template\Models\Template;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.whatsapp.api_token' => null]);
    $this->template = Template::factory()->published()->create();
});

/** Signs in a client on the given plan. */
function signIn(Client $client): Client
{
    test()->actingAs($client, 'client');

    return $client;
}

it('gives Starter one event, ever', function () {
    signIn(Client::factory()->starter()->create());

    $this->post('/app/events', ['title' => 'First', 'template_id' => $this->template->id])->assertSessionHas('success');
    Event::sole()->delete();

    $this->post('/app/events', ['title' => 'Second', 'template_id' => $this->template->id])
        ->assertSessionHas('error', 'The Starter plan includes one event. Upgrade to Celebration or Business to create more.');

    expect(Event::withTrashed()->count())->toBe(1);
});

it('uses one Celebration event credit per event', function () {
    $client = signIn(Client::factory()->celebration(1)->create());

    $this->post('/app/events', ['title' => 'Wedding', 'template_id' => $this->template->id])->assertSessionHas('success');
    expect($client->fresh()->event_credits)->toBe(0);

    $this->post('/app/events', ['title' => 'Another', 'template_id' => $this->template->id])
        ->assertSessionHas('error', 'You have no event credits left. Buy another event to create one.');
});

it('lets an active subscription create events, but not an ended one', function () {
    signIn(Client::factory()->business()->create());
    $this->post('/app/events', ['title' => 'One', 'template_id' => $this->template->id])->assertSessionHas('success');
    $this->post('/app/events', ['title' => 'Two', 'template_id' => $this->template->id])->assertSessionHas('success');

    $ended = signIn(Client::factory()->business(now()->subDay())->create());
    $this->post('/app/events', ['title' => 'Three', 'template_id' => $this->template->id])
        ->assertSessionHas('error', "Your Business plan ended on {$ended->plan_expires_at->format('j M Y')}. Renew it to create new events.");
});

it('limits guests to the plan, plus extra guests bought', function () {
    $client = signIn(Client::factory()->starter()->create());
    $event = Event::factory()->for($client)->create();
    Guest::factory()->for($event)->count(50)->create();

    $this->post("/app/events/{$event->id}/guests", ['name' => 'One too many', 'phone' => '+15550109999'])
        ->assertSessionHas('error', 'This event allows up to 50 guests on the Starter plan. Upgrade your plan for more guests.');

    $client->update(['plan' => 'celebration']);
    $event->update(['extra_guests' => 0]);
    expect($event->fresh()->guestLimit())->toBe(500);
    $event->update(['extra_guests' => 200]);
    expect($event->fresh()->guestLimit())->toBe(700);
});

it('refuses public registration on Starter', function () {
    $client = signIn(Client::factory()->starter()->create());

    $this->post('/app/events', ['title' => 'Open', 'template_id' => $this->template->id, 'registration_type' => 'open'])
        ->assertSessionHas('error', 'Public registration is available from the Celebration plan.');

    $event = Event::factory()->for($client)->create();
    $this->patch("/app/events/{$event->id}", ['registration_type' => 'open'])
        ->assertSessionHas('error', 'Public registration is available from the Celebration plan.');

    expect($event->fresh()->registration_type->value)->toBe('guest_list_only');
});

it('gives Starter one invitation and one reminder per guest', function () {
    $client = signIn(Client::factory()->starter()->create());
    $event = Event::factory()->for($client)->published()->create();
    $guest = Guest::factory()->for($event)->create(['name' => 'Priya']);

    $this->post("/app/guests/{$guest->id}/rsvps", ['send' => true])->assertSessionHas('success');
    $rsvp = Rsvp::sole();

    $this->post("/app/rsvps/{$rsvp->id}/resend")->assertSessionHas('error', 'Priya has had all 1 invitation for this event.');
    $this->post("/app/rsvps/{$rsvp->id}/remind")->assertSessionHas('success');
    $this->post("/app/rsvps/{$rsvp->id}/remind")->assertSessionHas('error', 'Priya has had all 1 reminder for this event.');
});

it('gives Enterprise higher message limits, which an event override still beats', function () {
    $event = Event::factory()->for(Client::factory()->enterprise())->create();
    expect($event->invitationLimit())->toBe(5)->and($event->reminderLimit())->toBe(5);

    $event->update(['max_invitations_per_guest' => 2]);
    expect($event->fresh()->invitationLimit())->toBe(2);
});

it('locks the Seating, RSVPs and Messages pages on Starter without loading their data', function (string $path, string $component, string $data) {
    $client = signIn(Client::factory()->starter()->create());
    $event = Event::factory()->for($client)->create();

    $this->get(str_replace('{event}', $event->id, $path))->assertInertia(fn (Assert $page) => $page
        ->component($component)
        ->where('locked', true)
        ->where('event.data.id', $event->id)
        ->missing($data));
})->with([
    'seating page' => ['/app/events/{event}/seating', 'client/events/seating', 'tables'],
    'RSVPs tab' => ['/app/events/{event}/rsvps', 'client/rsvps/index', 'rsvps'],
    'Messages tab' => ['/app/events/{event}/notifications', 'client/notifications/index', 'notifications'],
]);

it('keeps a message\'s details off the Starter plan', function () {
    $client = signIn(Client::factory()->starter()->create());
    $event = Event::factory()->for($client)->create();
    $notification = Notification::factory()->for(Guest::factory()->for($event))->create(['event_id' => $event->id]);

    $this->get("/app/notifications/{$notification->id}")->assertForbidden();
});

it('flashes the upgrade message for seating writes on Starter', function () {
    $client = signIn(Client::factory()->starter()->create());
    $table = EventTable::factory()->for(Event::factory()->for($client))->create();

    $this->from('/app/events')->patch("/app/tables/{$table->id}", ['name' => 'A', 'seat_count' => 4, 'shape' => 'round'])
        ->assertSessionHas('error', 'Seating is available from the Celebration plan.');
});

it('opens Seating, RSVPs and Messages on Celebration', function () {
    $client = signIn(Client::factory()->celebration()->create());
    $event = Event::factory()->for($client)->create();

    foreach (['seating', 'rsvps', 'notifications'] as $page) {
        $this->get("/app/events/{$event->id}/{$page}")->assertInertia(fn (Assert $inertia) => $inertia->missing('locked'));
    }
});

it('shares the plan and what is left with the dashboard', function () {
    $client = signIn(Client::factory()->starter()->create());
    Event::factory()->for($client)->create();

    $this->get('/app/events')->assertInertia(fn (Assert $page) => $page
        ->where('auth.client.plan.plan', 'starter')
        ->where('auth.client.plan.events_used', 1)
        ->where('auth.client.plan.events_allowed', 1)
        ->where('auth.client.plan.can_create_event', false)
        ->where('auth.client.plan.max_guests_per_event', 50)
        ->where('auth.client.plan.features', [])
        ->where('auth.client.plan.message_limits', ['invitations' => 1, 'reminders' => 1]));
});

it('shows the client their membership', function () {
    signIn(Client::factory()->celebration(2)->create());

    $this->get('/app/membership')->assertInertia(fn (Assert $page) => $page
        ->component('client/membership')
        ->where('auth.client.plan.plan', 'celebration')
        ->where('auth.client.plan.event_credits', 2));
});

it('sends guests from the membership page to the login page', function () {
    $this->get('/app/membership')->assertRedirect('/app/login');
});

it('flashes the upgrade message for floor plan writes on Starter', function () {
    $client = signIn(Client::factory()->starter()->create());
    $element = VenueElement::factory()->for(Event::factory()->for($client))->create();

    $this->from('/app/events')->delete("/app/venue-elements/{$element->id}")
        ->assertSessionHas('error', 'Seating is available from the Celebration plan.');
    $this->from('/app/events')->patch("/app/events/{$element->event_id}/seating/layout", ['tables' => [], 'elements' => []])
        ->assertSessionHas('error', 'Seating is available from the Celebration plan.');
});
