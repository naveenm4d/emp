<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffMember;
use Inertia\Testing\AssertableInertia as Assert;

it('logs staff in with the staff guard only', function () {
    $staff = StaffMember::factory()->create();

    $this->post('/internal/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect('/internal');

    $this->assertAuthenticatedAs($staff, 'staff');
    $this->assertGuest('client');
    expect($staff->fresh()->last_login_at)->not->toBeNull();
});

it('does not let inactive staff log in', function () {
    $staff = StaffMember::factory()->inactive()->create();

    $this->post('/internal/login', ['email' => $staff->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest('staff');
});

it('logs out staff who are deactivated mid-session', function () {
    $staff = StaffMember::factory()->create();
    $this->actingAs($staff, 'staff');

    $staff->update(['is_active' => false]);

    $this->get('/internal')->assertRedirect('/internal/login');
    $this->assertGuest('staff');
});

it('keeps clients out of the internal console', function () {
    $this->actingAs(Client::factory()->create(), 'client');

    $this->get('/internal')->assertRedirect('/internal/login');
});

it('keeps staff out of the client dashboard', function () {
    $this->actingAs(StaffMember::factory()->superAdmin()->create(), 'staff');

    $this->get('/app/events')->assertRedirect('/app/login');
});

it('enforces staff permissions on routes', function () {
    $this->actingAs(StaffMember::factory()->role(StaffRole::Viewer)->create(), 'staff');

    $this->get('/internal/clients')->assertOk();
    $this->get('/internal/staff')->assertForbidden();
    $this->get('/internal/notifications/failed')->assertForbidden();
});

it('lets super admins through every permission', function () {
    $this->actingAs(StaffMember::factory()->superAdmin()->withPermissions([])->create(), 'staff');

    $this->get('/internal/staff')->assertOk();
    $this->get('/internal/notifications/failed')->assertOk();
});

it('shows platform stats to staff with stats.read', function () {
    $this->actingAs(StaffMember::factory()->withPermissions([StaffPermission::StatsRead])->create(), 'staff');

    $this->get('/internal')
        ->assertInertia(fn (Assert $page) => $page
            ->component('internal/dashboard')
            ->where('stats.clients', 0)
            ->has('stats.eventsByState'));
});

it('hides stats from staff without stats.read', function () {
    $this->actingAs(StaffMember::factory()->withPermissions([])->create(), 'staff');

    $this->get('/internal')->assertInertia(fn (Assert $page) => $page->where('stats', null));
});

it('creates staff members with role default permissions', function () {
    $this->actingAs(StaffMember::factory()->superAdmin()->create(), 'staff');

    $this->post('/internal/staff', [
        'name' => 'New Admin',
        'email' => 'new@emp.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin',
    ])->assertRedirect('/internal/staff');

    $member = StaffMember::where('email', 'new@emp.test')->sole();
    expect($member->role)->toBe(StaffRole::Admin)
        ->and($member->hasPermission(StaffPermission::NotificationsRetry))->toBeTrue()
        ->and($member->hasPermission(StaffPermission::StaffCreate))->toBeFalse();
});

it('lists events across all clients with their owner', function () {
    $event = Event::factory()->create();
    $this->actingAs(StaffMember::factory()->withPermissions([StaffPermission::EventsRead])->create(), 'staff');

    $this->get('/internal/events')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('internal/events/index')
            ->where('events.data.0.client.id', $event->client_id));
});

it('lists clients with their event counts', function () {
    Event::factory()->count(2)->create(['client_id' => Client::factory()->create()->id]);
    $this->actingAs(StaffMember::factory()->withPermissions([StaffPermission::ClientsRead])->create(), 'staff');

    $this->get('/internal/clients')
        ->assertInertia(fn (Assert $page) => $page->where('clients.data.0.events_count', 2));
});
