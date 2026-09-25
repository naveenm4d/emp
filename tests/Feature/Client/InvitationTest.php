<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\RsvpStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Invitation\Enums\InvitationStatus;
use App\Domains\Invitation\Models\Invitation;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Models\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.whatsapp.api_token' => null]);

    $this->client = Client::factory()->create();
    $this->event = Event::factory()->for($this->client)->published()->create(['title' => 'Gala']);
    $this->actingAs($this->client, 'client');
});

it('creates and sends an invitation over WhatsApp', function () {
    $guest = Guest::factory()->for($this->event)->create(['name' => 'Ada']);

    $this->post("/app/guests/{$guest->id}/invitations", ['send' => true])->assertSessionHas('success');

    $invitation = Invitation::sole();
    expect($invitation->status)->toBe(InvitationStatus::Sent)
        ->and(strlen($invitation->token))->toBe(64)
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and($guest->fresh()->rsvp_status)->toBe(RsvpStatus::Pending);

    // The queue runs synchronously in tests, so the mock provider already accepted it.
    $notification = Notification::sole();
    expect($notification->status)->toBe(NotificationStatus::Sent)
        ->and($notification->recipient)->toBe($guest->phone)
        ->and($notification->message)->toContain(url("/rsvp/{$invitation->token}"))
        ->and($notification->provider_message_id)->toStartWith('mock-wamid-');
});

it('only invites approved guests', function () {
    $guest = Guest::factory()->for($this->event)->status(ApprovalStatus::Pending)->create();

    $this->post("/app/guests/{$guest->id}/invitations")->assertSessionHas('error', 'Only approved guests can be invited.');
    expect(Invitation::count())->toBe(0);
});

it('allows only one active invitation per guest', function () {
    $guest = Guest::factory()->for($this->event)->create();
    Invitation::factory()->for($guest)->for($this->event)->create();

    $this->post("/app/guests/{$guest->id}/invitations")->assertSessionHas('error', 'This guest already has an active invitation.');
});

it('requires a phone number to send', function () {
    $guest = Guest::factory()->for($this->event)->withoutPhone()->create();
    $invitation = Invitation::factory()->for($guest)->for($this->event)->create();

    $this->post("/app/invitations/{$invitation->id}/send")->assertSessionHas('error');
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Pending);
});

it('only resends sent invitations', function () {
    $guest = Guest::factory()->for($this->event)->create();
    $invitation = Invitation::factory()->for($guest)->for($this->event)->create();

    $this->post("/app/invitations/{$invitation->id}/resend")->assertSessionHas('error', 'Only sent invitations can be re-sent.');
});

it('expires an active invitation', function () {
    $guest = Guest::factory()->for($this->event)->create();
    $invitation = Invitation::factory()->for($guest)->for($this->event)->sent()->create();

    $this->post("/app/invitations/{$invitation->id}/expire")->assertSessionHas('success');
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Expired);
});

it('forbids acting on another client\'s invitation', function () {
    $invitation = Invitation::factory()->create();

    $this->post("/app/invitations/{$invitation->id}/send")->assertForbidden();
});

it('lists invitations with their guests and summary', function () {
    $guest = Guest::factory()->for($this->event)->create(['name' => 'Ada']);
    Invitation::factory()->for($guest)->for($this->event)->sent()->create();

    $this->get("/app/events/{$this->event->id}/invitations")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/invitations/index')
            ->where('invitations.data.0.guest.name', 'Ada')
            ->where('summary.sent', 1));
});

it('shows a message with its delivery log', function () {
    $guest = Guest::factory()->for($this->event)->create();
    $notification = Notification::factory()->for($guest)->for($this->event)->create();

    $this->get("/app/events/{$this->event->id}/notifications")->assertOk();
    $this->get("/app/notifications/{$notification->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('client/notifications/show'));
});
