<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Support\Facades\Hash;
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
