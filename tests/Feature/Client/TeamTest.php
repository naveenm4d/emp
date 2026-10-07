<?php

use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Enums\ClientUserRole;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Client\Notifications\ClientInvitation;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Template\Models\Template;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->client = Client::factory()->create(['name' => 'Nimal & Sanduni']);
    $this->owner = $this->client->owner;
    $this->wedding = Event::factory()->for($this->client)->create(['title' => 'Wedding']);
    $this->homecoming = Event::factory()->for($this->client)->create(['title' => 'Homecoming']);
});

/** @param array<string, mixed> $overrides */
function invite(array $overrides = []): array
{
    return [
        'name' => 'Kamal',
        'email' => 'kamal@example.com',
        'permissions' => ['guests.manage'],
        'all_events' => false,
        'event_ids' => [],
        ...$overrides,
    ];
}

it('invites a member with permissions and events, and emails the link', function () {
    $this->actingAs($this->owner, 'client')
        ->post('/app/team', invite(['event_ids' => [$this->wedding->id]]))
        ->assertSessionHas('success', 'Invitation sent to kamal@example.com.');

    $member = ClientUser::where('email', 'kamal@example.com')->sole();
    expect($member)
        ->client_id->toBe($this->client->id)
        ->role->toBe(ClientUserRole::Member)
        ->permissions->toBe(['guests.manage'])
        ->password->toBeNull()
        ->and($member->events->pluck('id')->all())->toBe([$this->wedding->id]);

    Notification::assertSentTo($member, ClientInvitation::class);
});

it('keeps each plan within its user limit, pending invitations included', function (string $plan, int $limit) {
    $client = Client::factory()->{$plan}()->create();
    ClientUser::factory()->for($client)->invited()->count($limit - 1)->create();

    $this->actingAs($client->owner, 'client')
        ->post('/app/team', invite())
        ->assertSessionHas('error');

    expect($client->users()->count())->toBe($limit);
})->with([
    'starter' => ['starter', 1],
    'celebration' => ['celebration', 2],
    'business' => ['business', 5],
]);

it('lets a Celebration account add the second person', function () {
    $client = Client::factory()->celebration()->create();

    $this->actingAs($client->owner, 'client')->post('/app/team', invite())->assertSessionHas('success');

    expect($client->users()->count())->toBe(2);
});

it('uses the limit staff set for Enterprise, and none when unset', function () {
    $limited = Client::factory()->enterprise()->create(['user_limit' => 2]);
    ClientUser::factory()->for($limited)->create();
    $this->actingAs($limited->owner, 'client')->post('/app/team', invite())->assertSessionHas('error');

    $unlimited = Client::factory()->enterprise()->create();
    ClientUser::factory()->for($unlimited)->count(6)->create();
    $this->actingAs($unlimited->owner, 'client')->post('/app/team', invite())->assertSessionHas('success');
});

it('rejects an email that already signs in, and events of another account', function () {
    $other = Event::factory()->create();

    $this->actingAs($this->owner, 'client')
        ->post('/app/team', invite(['email' => $this->owner->email, 'event_ids' => [$other->id]]))
        ->assertSessionHasErrors(['email', 'event_ids.0']);
});

it('accepts an invitation: sets the password and signs in', function () {
    $this->actingAs($this->owner, 'client')->post('/app/team', invite());
    auth('client')->logout();
    $member = ClientUser::where('email', 'kamal@example.com')->sole();

    $token = null;
    Notification::assertSentTo($member, ClientInvitation::class, function (ClientInvitation $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->get("/app/invitation/{$token}?email=kamal@example.com")
        ->assertInertia(fn (Assert $page) => $page->component('client/auth/accept-invitation')->where('email', 'kamal@example.com'));

    $this->post('/app/invitation', [
        'token' => $token,
        'email' => 'kamal@example.com',
        'password' => 'S3cret-pass!',
        'password_confirmation' => 'S3cret-pass!',
    ])->assertRedirect('/app');

    $this->assertAuthenticatedAs($member->fresh(), 'client');
    expect($member->fresh()->joined_at)->not->toBeNull();
});

it('refuses a wrong invitation token, and pending members can\'t sign in', function () {
    $member = ClientUser::factory()->for($this->client)->invited()->create();

    $this->post('/app/invitation', [
        'token' => 'nope', 'email' => $member->email, 'password' => 'S3cret-pass!', 'password_confirmation' => 'S3cret-pass!',
    ])->assertSessionHasErrors('email');

    $this->post('/app/login', ['email' => $member->email, 'password' => ''])->assertSessionHasErrors();
    $this->assertGuest('client');
});

it('shows members only the events they were given', function () {
    $member = ClientUser::factory()->for($this->client)->forEvents($this->wedding)->create();
    $this->actingAs($member, 'client');

    $this->get('/app/events')->assertInertia(fn (Assert $page) => $page
        ->has('events.data', 1)
        ->where('events.data.0.id', $this->wedding->id));
    $this->get('/app')->assertInertia(fn (Assert $page) => $page->has('events.data', 1));

    $this->get("/app/events/{$this->wedding->id}")->assertOk();
    $this->get("/app/events/{$this->homecoming->id}")->assertForbidden();
    $this->get("/app/events/{$this->homecoming->id}/guests")->assertForbidden();
});

it('lets members with all events see every event', function () {
    $member = ClientUser::factory()->for($this->client)->allEvents()->create();

    $this->actingAs($member, 'client')->get('/app/events')->assertInertia(fn (Assert $page) => $page->has('events.data', 2));
});

it('enforces each permission', function () {
    $member = ClientUser::factory()->for($this->client)->forEvents($this->wedding)->create();
    $guest = Guest::factory()->for($this->wedding)->create();
    $this->actingAs($member, 'client');

    $this->post("/app/events/{$this->wedding->id}/guests", ['name' => 'Ada', 'phone' => '+15550100200'])->assertForbidden();
    $this->patch("/app/events/{$this->wedding->id}", ['title' => 'Changed'])->assertForbidden();
    $this->delete("/app/events/{$this->wedding->id}")->assertForbidden();
    $this->post("/app/events/{$this->wedding->id}/tables", ['name' => 'A', 'seat_count' => 8, 'shape' => 'round'])->assertForbidden();
    $this->post("/app/guests/{$guest->id}/rsvps")->assertForbidden();
    $this->get('/app/events/create')->assertForbidden();
    $this->get('/app/team')->assertForbidden();

    $member->update(['permissions' => [ClientPermission::GuestsManage->value, ClientPermission::SeatingManage->value]]);

    $this->post("/app/events/{$this->wedding->id}/guests", ['name' => 'Ada', 'phone' => '+15550100200'])->assertSessionHas('success');
    $this->post("/app/events/{$this->wedding->id}/tables", ['name' => 'A', 'seat_count' => 8, 'shape' => 'round'])->assertSessionHas('success');
});

it('gives a member the events they create', function () {
    $member = ClientUser::factory()->for($this->client)->withPermissions([ClientPermission::EventsCreate])->create();

    $template = Template::factory()->published()->create();
    $this->actingAs($member, 'client')->get('/app/events/create')->assertOk();

    $this->post('/app/events', ['title' => 'Engagement', 'template_id' => $template->id])->assertRedirect();

    $event = Event::where('title', 'Engagement')->sole();
    expect($event->client_id)->toBe($this->client->id)
        ->and($member->events()->pluck('events.id')->all())->toBe([$event->id]);
});

it('updates and removes members, but never the owner or yourself', function () {
    $manager = ClientUser::factory()->for($this->client)->withPermissions([ClientPermission::TeamManage])->create();
    $member = ClientUser::factory()->for($this->client)->create();
    $this->actingAs($manager, 'client');

    $this->patch("/app/team/{$member->id}", ['permissions' => ['messages.send'], 'all_events' => true])
        ->assertSessionHas('success');
    expect($member->fresh())->permissions->toBe(['messages.send'])->all_events->toBeTrue();

    $this->patch("/app/team/{$this->owner->id}", ['permissions' => [], 'all_events' => false])->assertForbidden();
    $this->patch("/app/team/{$manager->id}", ['permissions' => [], 'all_events' => false])->assertForbidden();
    $this->delete("/app/team/{$this->owner->id}")->assertForbidden();

    $this->delete("/app/team/{$member->id}")->assertSessionHas('success');
    expect(ClientUser::find($member->id))->toBeNull();
});

it('doesn\'t let one account manage another account\'s users', function () {
    $stranger = ClientUser::factory()->create();

    $this->actingAs($this->owner, 'client')->delete("/app/team/{$stranger->id}")->assertForbidden();
});

it('hands the account to another user, only from the owner', function () {
    $member = ClientUser::factory()->for($this->client)->withPermissions([ClientPermission::TeamManage])->create();

    $this->actingAs($member, 'client')->post("/app/team/{$this->owner->id}/owner")->assertForbidden();

    $this->actingAs($this->owner, 'client')->post("/app/team/{$member->id}/owner")->assertSessionHas('success');

    expect($member->fresh()->isOwner())->toBeTrue()
        ->and($this->owner->fresh())
        ->role->toBe(ClientUserRole::Member)
        ->all_events->toBeTrue()
        ->permissions->toEqualCanonicalizing(ClientPermission::values());
});

it('lists the team with the shared user usage', function () {
    ClientUser::factory()->for($this->client)->invited()->create(['name' => 'Pending Person']);

    $this->actingAs($this->owner, 'client')->get('/app/team')->assertInertia(fn (Assert $page) => $page
        ->component('client/team/index')
        ->has('members.data', 2)
        ->where('members.data.0.is_owner', true)
        ->where('members.data.1.pending', true)
        ->has('events', 2)
        ->where('auth.client.plan.users_used', 2)
        ->where('auth.client.plan.users_allowed', 5)
        ->where('auth.user.is_owner', true));
});
