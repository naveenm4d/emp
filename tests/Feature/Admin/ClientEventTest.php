<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Enums\EventType;
use App\Domains\Event\Models\Event;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffMember;
use App\Domains\Template\Enums\TemplateType;
use App\Domains\Template\Models\Template;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->template = Template::factory()->published()->create();
    $this->actingAs(StaffMember::factory()->role(StaffRole::Admin)->create(), 'staff');

    $this->eventUrl = fn (Event $event, string $suffix = '') => "/admin/clients/{$this->client->id}/events/{$event->id}{$suffix}";
});

it('shows the create form with the client\'s templates and event types', function () {
    $this->get("/admin/clients/{$this->client->id}/events/create")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/events/create')
            ->has('templates.data', 1)
            ->has('eventTypes', count(EventType::cases())));
});

it('creates a draft event for the client pinned to the template\'s latest version', function () {
    $this->post("/admin/clients/{$this->client->id}/events", [
        'title' => 'Anna & Raj',
        'template_id' => $this->template->id,
        'event_type' => 'wedding',
    ])->assertSessionHas('success', 'Event created as draft.');

    $event = Event::sole();
    expect($event->client_id)->toBe($this->client->id)
        ->and($event->state)->toBe(EventState::Draft)
        ->and($event->template_version_id)->toBe($this->template->latest_version_id)
        ->and($event->event_type)->toBe(EventType::Wedding);
});

it('rejects another client\'s custom template', function () {
    $custom = Template::factory()->published()->create([
        'type' => TemplateType::Custom,
        'client_id' => Client::factory()->create()->id,
    ]);

    $this->post("/admin/clients/{$this->client->id}/events", ['title' => 'X', 'template_id' => $custom->id])
        ->assertSessionHasErrors(['template_id' => 'The selected template is not available.']);

    expect(Event::count())->toBe(0);
});

it('updates an event\'s details', function () {
    $event = Event::factory()->for($this->client)->create(['template_version_id' => $this->template->latest_version_id]);

    $this->patch(($this->eventUrl)($event), ['title' => 'Renamed', 'event_type' => 'birthday'])
        ->assertSessionHas('success', 'Event updated.');

    expect($event->fresh())
        ->title->toBe('Renamed')
        ->event_type->toBe(EventType::Birthday);
});

it('publishes an event and opens and closes its registration', function () {
    $event = Event::factory()->for($this->client)->create(['template_version_id' => $this->template->latest_version_id, 'registration_type' => 'open']);

    $this->patch(($this->eventUrl)($event, '/state'), ['state' => 'published'])->assertSessionHas('success');
    expect($event->fresh()->state)->toBe(EventState::Published);

    $this->post(($this->eventUrl)($event, '/registration/open'))->assertSessionHas('success', 'Registration opened.');
    expect($event->fresh()->registration_open)->toBeTrue();

    $this->post(($this->eventUrl)($event, '/registration/close'))->assertSessionHas('success', 'Registration closed.');
    expect($event->fresh()->registration_open)->toBeFalse();
});

it('deletes an event and returns to the client', function () {
    $event = Event::factory()->for($this->client)->create();

    $this->delete(($this->eventUrl)($event))->assertRedirect("/admin/clients/{$this->client->id}");

    $this->assertSoftDeleted($event);
});

it('returns 404 for an event that belongs to another client', function (string $method, string $suffix) {
    $other = Event::factory()->create();

    $this->{$method}(($this->eventUrl)($other, $suffix), ['state' => 'published', 'title' => 'X'])->assertNotFound();

    expect($other->fresh())->not->toBeNull()->title->not->toBe('X');
})->with([
    'edit' => ['get', '/edit'],
    'update' => ['patch', ''],
    'state' => ['patch', '/state'],
    'delete' => ['delete', ''],
]);

it('previews templates as the client would see them', function () {
    $own = Template::factory()->published()->create(['type' => TemplateType::Custom, 'client_id' => $this->client->id]);
    $others = Template::factory()->published()->create(['type' => TemplateType::Custom, 'client_id' => Client::factory()->create()->id]);

    $this->getJson("/admin/clients/{$this->client->id}/templates/{$own->id}/preview?title=Gala")
        ->assertOk()
        ->assertJsonPath('id', $own->id);

    $this->getJson("/admin/clients/{$this->client->id}/templates/{$others->id}/preview")->assertNotFound();
});

it('forbids viewers from managing a client\'s events', function () {
    $this->actingAs(StaffMember::factory()->role(StaffRole::Viewer)->create(), 'staff');

    $this->post("/admin/clients/{$this->client->id}/events", ['title' => 'X', 'template_id' => $this->template->id])
        ->assertForbidden();

    expect(Event::count())->toBe(0);
});

it('sets and clears an event\'s message limits', function () {
    config(['emp.max_invitations_per_guest' => 3, 'emp.max_reminders_per_guest' => 3]);
    $event = Event::factory()->for($this->client)->create();

    $this->patch(($this->eventUrl)($event, '/message-limits'), ['max_invitations_per_guest' => 1, 'max_reminders_per_guest' => 0])
        ->assertSessionHas('success', 'Message limits saved.');

    expect($event->fresh())->invitationLimit()->toBe(1)->reminderLimit()->toBe(0);

    $this->get(($this->eventUrl)($event, '/edit'))->assertInertia(fn (Assert $page) => $page
        ->where('event.data.message_limits.invitations_override', 1)
        ->where('event.data.message_limits.default_invitations', 3));

    $this->patch(($this->eventUrl)($event, '/message-limits'), ['max_invitations_per_guest' => null, 'max_reminders_per_guest' => null]);

    expect($event->fresh())->max_invitations_per_guest->toBeNull()->invitationLimit()->toBe(3);
});

it('validates message limits', function () {
    $event = Event::factory()->for($this->client)->create();

    $this->patch(($this->eventUrl)($event, '/message-limits'), ['max_invitations_per_guest' => 0, 'max_reminders_per_guest' => 21])
        ->assertSessionHasErrors([
            'max_invitations_per_guest' => 'The invitations per guest field must be at least 1.',
            'max_reminders_per_guest' => 'The reminders per guest field must not be greater than 20.',
        ]);
});

it('forbids viewers from changing message limits', function () {
    $this->actingAs(StaffMember::factory()->role(StaffRole::Viewer)->create(), 'staff');
    $event = Event::factory()->for($this->client)->create();

    $this->patch(($this->eventUrl)($event, '/message-limits'), ['max_invitations_per_guest' => 9])->assertForbidden();
});

it('does not let clients change their message limits', function () {
    $event = Event::factory()->for($this->client)->create();
    $this->actingAs($this->client, 'client');

    $this->patch("/app/events/{$event->id}", ['max_invitations_per_guest' => 50, 'max_reminders_per_guest' => 50]);

    expect($event->fresh())->max_invitations_per_guest->toBeNull()->max_reminders_per_guest->toBeNull();
});
