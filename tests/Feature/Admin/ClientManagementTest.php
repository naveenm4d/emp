<?php

use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Notifications\ClientInvitation;
use App\Domains\Event\Models\Event;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->client = Client::factory()->create(['email' => 'old@example.com']);
    $this->actingAs(StaffMember::factory()->role(StaffRole::Admin)->create(), 'staff');
});

it('shows a client with only their events', function () {
    Event::factory()->for($this->client)->count(2)->create();
    Event::factory()->create();

    $this->get("/admin/clients/{$this->client->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/clients/show')
            ->where('client.data.id', $this->client->id)
            ->has('events.data', 2));
});

it('updates the account and sets the owner\'s password', function () {
    $this->patch("/admin/clients/{$this->client->id}", [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'password' => 'N3w-password!',
        'password_confirmation' => 'N3w-password!',
    ])->assertRedirect()->assertSessionHas('success', 'Client updated.');

    $client = $this->client->fresh();
    expect($client->name)->toBe('New Name')
        ->and($client->email)->toBe('new@example.com')
        ->and(Hash::check('N3w-password!', $client->owner->password))->toBeTrue();
});

it('keeps the password when none is given', function () {
    $password = $this->client->owner->password;

    $this->patch("/admin/clients/{$this->client->id}", ['name' => 'Renamed', 'email' => 'old@example.com'])
        ->assertSessionHasNoErrors();

    expect($this->client->owner->fresh()->password)->toBe($password);
});

it('rejects an email another client uses', function () {
    Client::factory()->create(['email' => 'taken@example.com']);

    $this->patch("/admin/clients/{$this->client->id}", ['name' => 'X', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);
});

it('forbids staff without clients.update from editing a client', function () {
    $this->actingAs(StaffMember::factory()->role(StaffRole::Viewer)->create(), 'staff');

    $this->get("/admin/clients/{$this->client->id}")->assertOk();
    $this->patch("/admin/clients/{$this->client->id}", ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();

    expect($this->client->fresh()->name)->not->toBe('X');
});

it('creates a client whose owner is invited to choose a password', function () {
    Notification::fake();

    $response = $this->post('/admin/clients', ['name' => 'Acme Weddings', 'email' => 'Hello@Acme.test']);

    $client = Client::where('email', 'hello@acme.test')->firstOrFail();
    $response->assertRedirect("/admin/clients/{$client->id}")->assertSessionHas('success');

    $owner = $client->owner;
    expect($client->plan)->toBe(ClientPlan::Starter)
        ->and($owner->email)->toBe('hello@acme.test')
        ->and($owner->password)->toBeNull()
        ->and($owner->isPending())->toBeTrue();
    Notification::assertSentTo($owner, ClientInvitation::class);
});

it('creates a client whose owner can sign in with the given password', function () {
    Notification::fake();

    $this->post('/admin/clients', [
        'name' => 'Acme Weddings',
        'email' => 'hello@acme.test',
        'password' => 'N3w-password!',
        'password_confirmation' => 'N3w-password!',
    ])->assertSessionHasNoErrors();

    $owner = Client::where('email', 'hello@acme.test')->firstOrFail()->owner;
    expect(Hash::check('N3w-password!', $owner->password))->toBeTrue()
        ->and($owner->isPending())->toBeFalse();
    Notification::assertNotSentTo($owner, ClientInvitation::class);
});

it('rejects a new client with an email already in use', function () {
    $this->post('/admin/clients', ['name' => 'Copy', 'email' => 'old@example.com'])
        ->assertSessionHasErrors('email');

    expect(Client::where('name', 'Copy')->exists())->toBeFalse();
});

it('forbids staff without clients.create from creating a client', function () {
    $this->actingAs(StaffMember::factory()->role(StaffRole::Viewer)->create(), 'staff');

    $this->get('/admin/clients/create')->assertForbidden();
    $this->post('/admin/clients', ['name' => 'Acme', 'email' => 'hello@acme.test'])->assertForbidden();

    expect(Client::where('email', 'hello@acme.test')->exists())->toBeFalse();
});
