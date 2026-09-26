<?php

use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the login page', function () {
    $this->get('/app/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('client/auth/login'));
});

it('registers a client and signs them in', function () {
    $this->post('/app/register', [
        'name' => 'Grace Hopper',
        'email' => 'Grace@Example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect('/app');

    $client = Client::sole();
    expect($client->email)->toBe('grace@example.com')
        ->and($client->fresh()->plan)->toBe(ClientPlan::Starter);
    $this->assertAuthenticatedAs($client, 'client');
});

it('logs a client in and out', function () {
    $client = Client::factory()->create();

    $this->post('/app/login', ['email' => $client->email, 'password' => 'password'])->assertRedirect('/app');
    $this->assertAuthenticatedAs($client, 'client');

    $this->post('/app/logout')->assertRedirect('/app/login');
    $this->assertGuest('client');
});

it('rejects bad credentials', function () {
    $client = Client::factory()->create();

    $this->post('/app/login', ['email' => $client->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest('client');
});

it('redirects guests to the client login', function () {
    $this->get('/app/events')->assertRedirect('/app/login');
});
