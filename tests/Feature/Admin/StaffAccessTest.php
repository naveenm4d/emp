<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Models\Event;
use App\Domains\Notification\Models\Notification;
use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffMember;
use Inertia\Testing\AssertableInertia as Assert;

it('logs staff in with the staff guard only', function () {
    $staff = StaffMember::factory()->create();

    $this->post('/admin/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($staff, 'staff');
    $this->assertGuest('client');
    expect($staff->fresh()->last_login_at)->not->toBeNull();
});

it('does not let inactive staff log in', function () {
    $staff = StaffMember::factory()->inactive()->create();

    $this->post('/admin/login', ['email' => $staff->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest('staff');
});

it('logs out staff who are deactivated mid-session', function () {
    $staff = StaffMember::factory()->create();
    $this->actingAs($staff, 'staff');

    $staff->update(['is_active' => false]);

    $this->get('/admin')->assertRedirect('/admin/login');
    $this->assertGuest('staff');
});

it('keeps clients out of the admin console', function () {
    $this->actingAs(Client::factory()->create(), 'client');

    $this->get('/admin')->assertRedirect('/admin/login');
});

it('keeps staff out of the client dashboard', function () {
    $this->actingAs(StaffMember::factory()->superAdmin()->create(), 'staff');

    $this->get('/app/events')->assertRedirect('/app/login');
});

it('enforces staff permissions on routes', function () {
    $this->actingAs(StaffMember::factory()->role(StaffRole::Viewer)->create(), 'staff');

    $this->get('/admin/clients')->assertOk();
    $this->get('/admin/staff')->assertForbidden();
    $this->get('/admin/notifications/failed')->assertForbidden();
});

it('lets super admins through every permission', function () {
    $this->actingAs(StaffMember::factory()->superAdmin()->withPermissions([])->create(), 'staff');

    $this->get('/admin/staff')->assertOk();
    $this->get('/admin/notifications/failed')->assertOk();
});

it('shows platform stats to staff with stats.read', function () {
    $this->actingAs(StaffMember::factory()->withPermissions([StaffPermission::StatsRead])->create(), 'staff');

    $this->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('stats.clients', 0)
            ->has('stats.eventsByState'));
});

it('hides stats from staff without stats.read', function () {
    $this->actingAs(StaffMember::factory()->withPermissions([])->create(), 'staff');

    $this->get('/admin')->assertInertia(fn (Assert $page) => $page->where('stats', null));
});

it('creates staff members with role default permissions', function () {
    $this->actingAs(StaffMember::factory()->superAdmin()->create(), 'staff');

    $this->post('/admin/staff', [
        'name' => 'New Admin',
        'email' => 'new@emp.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin',
    ])->assertRedirect('/admin/staff');

    $member = StaffMember::where('email', 'new@emp.test')->sole();
    expect($member->role)->toBe(StaffRole::Admin)
        ->and($member->hasPermission(StaffPermission::NotificationsRetry))->toBeTrue()
        ->and($member->hasPermission(StaffPermission::StaffCreate))->toBeFalse();
});

it('lists events across all clients with their owner', function () {
    $event = Event::factory()->create();
    $this->actingAs(StaffMember::factory()->withPermissions([StaffPermission::EventsRead])->create(), 'staff');

    $this->get('/admin/events')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/events/index')
            ->where('events.data.0.client.id', $event->client_id));
});

it('lists upcoming events by default and filters by registration type', function () {
    $open = Event::factory()->create(['title' => 'Open day', 'registration_type' => RegistrationType::Open]);
    Event::factory()->create(['title' => 'Private', 'registration_type' => RegistrationType::GuestListOnly]);
    Event::factory()->create(['title' => 'Held', 'event_date' => now()->subWeek()->toDateString()]);
    $this->actingAs(StaffMember::factory()->withPermissions([StaffPermission::EventsRead])->create(), 'staff');

    $this->get('/admin/events')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.period', 'upcoming')
            ->has('events.data', 2));

    $this->get('/admin/events?period=all&registration_type=open')
        ->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.id', $open->id));
});

it('shares the failed message count only with staff who can read notifications', function (array $permissions, ?int $expected) {
    Notification::factory()->failed()->count(2)->create();
    $this->actingAs(StaffMember::factory()->withPermissions([StaffPermission::EventsRead, ...$permissions])->create(), 'staff');

    $response = $this->get('/admin/events');

    $response->assertInertia(fn (Assert $page) => $page->where('failedMessages', $expected));
})->with([
    'can read notifications' => [[StaffPermission::NotificationsRead], 2],
    'cannot' => [[], null],
]);

it('lists clients with their event counts', function () {
    Event::factory()->count(2)->create(['client_id' => Client::factory()->create()->id]);
    $this->actingAs(StaffMember::factory()->withPermissions([StaffPermission::ClientsRead])->create(), 'staff');

    $this->get('/admin/clients')
        ->assertInertia(fn (Assert $page) => $page->where('clients.data.0.events_count', 2));
});
