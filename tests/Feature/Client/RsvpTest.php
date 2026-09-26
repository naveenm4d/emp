<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Enums\NotificationKind;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Models\Notification;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Models\Rsvp;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.whatsapp.api_token' => null]);

    $this->client = Client::factory()->create();
    $this->event = Event::factory()->for($this->client)->published()->create(['title' => 'Gala']);
    $this->actingAs($this->client, 'client');
});

it('creates and sends an RSVP link over WhatsApp', function () {
    $guest = Guest::factory()->for($this->event)->create(['name' => 'Ada']);

    $this->post("/app/guests/{$guest->id}/rsvps", ['send' => true])->assertSessionHas('success');

    $rsvp = Rsvp::sole();
    expect($rsvp->status)->toBe(RsvpStatus::Sent)
        ->and(strlen($rsvp->token))->toBe(64)
        ->and($rsvp->expires_at->isFuture())->toBeTrue()
        ->and($guest->fresh()->rsvp_status)->toBe(GuestRsvpStatus::Pending);

    // The queue runs synchronously in tests, so the mock provider already accepted it.
    $notification = Notification::sole();
    expect($notification->status)->toBe(NotificationStatus::Sent)
        ->and($notification->recipient)->toBe($guest->phone)
        ->and($notification->message)->toContain($rsvp->rsvpUrl())
        ->and($notification->provider_message_id)->toStartWith('mock-wamid-')
        ->and($notification->kind)->toBe(NotificationKind::RsvpInvitation);
});

it('sends the RSVP message from the guest, the event or the default', function (?string $eventMessage, ?string $guestMessage, string $expected) {
    $this->event->update(['invitation_message' => $eventMessage, 'location_name' => 'Galle Face']);
    $guest = Guest::factory()->for($this->event)->create(['name' => 'Anissa Jr.', 'invitation_message' => $guestMessage]);

    $this->post("/app/guests/{$guest->id}/rsvps", ['send' => true])->assertSessionHas('success');

    $link = Rsvp::sole()->rsvpUrl();
    expect(Notification::sole()->message)->toBe(str_replace(':link', $link, $expected));
})->with([
    'default' => [null, null, "Hi Anissa Jr., you're invited to Gala! Please RSVP here: :link"],
    'event message' => ['Hi {{guest.name}}, You are invited to {{ event.title }} at {{ event.venue }}! Please RSVP: {{ rsvp.link }}', null, 'Hi Anissa Jr., You are invited to Gala at Galle Face! Please RSVP: :link'],
    'guest message wins' => ['Event text {{ rsvp.link }}', 'Hi {{ guest.name }}, you are welcome to our event: {{ rsvp.link }}', 'Hi Anissa Jr., you are welcome to our event: :link'],
    'link appended when missing' => ['See you there, {{ guest.name }}! {{ unknown }}', null, "See you there, Anissa Jr.! {{ unknown }}\n\n:link"],
]);

it('only invites approved guests', function () {
    $guest = Guest::factory()->for($this->event)->status(ApprovalStatus::Pending)->create();

    $this->post("/app/guests/{$guest->id}/rsvps")->assertSessionHas('error', 'Only approved guests can be invited.');
    expect(Rsvp::count())->toBe(0);
});

it('allows only one active RSVP link per guest', function () {
    $guest = Guest::factory()->for($this->event)->create();
    Rsvp::factory()->for($guest)->for($this->event)->create();

    $this->post("/app/guests/{$guest->id}/rsvps")->assertSessionHas('error', 'This guest already has an active RSVP link.');
});

it('requires a phone number to send', function () {
    $guest = Guest::factory()->for($this->event)->withoutPhone()->create();
    $rsvp = Rsvp::factory()->for($guest)->for($this->event)->create();

    $this->post("/app/rsvps/{$rsvp->id}/send")->assertSessionHas('error');
    expect($rsvp->fresh()->status)->toBe(RsvpStatus::Pending);
});

it('only resends sent RSVP links', function () {
    $guest = Guest::factory()->for($this->event)->create();
    $rsvp = Rsvp::factory()->for($guest)->for($this->event)->create();

    $this->post("/app/rsvps/{$rsvp->id}/resend")->assertSessionHas('error', 'Only sent RSVP links can be re-sent.');
});

it('expires an active RSVP link', function () {
    $guest = Guest::factory()->for($this->event)->create();
    $rsvp = Rsvp::factory()->for($guest)->for($this->event)->sent()->create();

    $this->post("/app/rsvps/{$rsvp->id}/expire")->assertSessionHas('success');
    expect($rsvp->fresh()->status)->toBe(RsvpStatus::Expired);
});

it('forbids acting on another client\'s RSVP link', function () {
    $rsvp = Rsvp::factory()->create();

    $this->post("/app/rsvps/{$rsvp->id}/send")->assertForbidden();
});

it('lists RSVP links with their guests and summary', function () {
    $guest = Guest::factory()->for($this->event)->create(['name' => 'Ada']);
    Rsvp::factory()->for($guest)->for($this->event)->sent()->create();

    $this->get("/app/events/{$this->event->id}/rsvps")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/rsvps/index')
            ->where('rsvps.data.0.guest.name', 'Ada')
            ->where('summary.sent', 1));
});

it('shows the latest message status of each RSVP link on the RSVP and guest lists', function () {
    $guest = Guest::factory()->for($this->event)->create(['name' => 'Ada']);
    $rsvp = Rsvp::factory()->for($guest)->for($this->event)->sent()->create();
    Notification::factory()->for($guest)->for($this->event)->create(['rsvp_id' => $rsvp->id, 'status' => NotificationStatus::Sent, 'created_at' => now()->subMinute()]);
    Notification::factory()->for($guest)->for($this->event)->create(['rsvp_id' => $rsvp->id, 'status' => NotificationStatus::Read]);

    $this->get("/app/events/{$this->event->id}/rsvps")
        ->assertInertia(fn (Assert $page) => $page->where('rsvps.data.0.message.status', 'read'));

    $this->get("/app/events/{$this->event->id}/guests")
        ->assertInertia(fn (Assert $page) => $page->where('guests.data.0.latest_rsvp.message.status', 'read'));
});

it('links the sent message to its RSVP link', function () {
    $guest = Guest::factory()->for($this->event)->create();

    $this->post("/app/guests/{$guest->id}/rsvps", ['send' => true]);

    expect(Notification::sole()->rsvp_id)->toBe(Rsvp::sole()->id);
});

it('reminds a guest with the guest, event or default reminder text', function (?string $eventReminder, ?string $guestReminder, string $expected) {
    $this->event->update(['reminder_message' => $eventReminder]);
    $guest = Guest::factory()->for($this->event)->create(['name' => 'Ada', 'reminder_message' => $guestReminder]);
    $rsvp = Rsvp::factory()->for($guest)->for($this->event)->sent()->create();

    $this->post("/app/rsvps/{$rsvp->id}/remind")->assertSessionHas('success', 'Reminder sent.');

    $link = $rsvp->rsvpUrl();
    expect(Notification::sole())
        ->message->toBe(str_replace(':link', $link, $expected))
        ->rsvp_id->toBe($rsvp->id)
        ->kind->toBe(NotificationKind::RsvpReminder)
        ->and($rsvp->fresh())
        ->reminder_count->toBe(1)
        ->last_reminded_at->not->toBeNull()
        ->status->toBe(RsvpStatus::Sent);
})->with([
    'default' => [null, null, 'Hi Ada, a friendly reminder to RSVP for Gala: :link'],
    'event reminder' => ['Only a few seats left, {{ guest.name }}! {{ rsvp.link }}', null, 'Only a few seats left, Ada! :link'],
    'guest reminder wins' => ['Event text {{ rsvp.link }}', 'Ada, please reply', "Ada, please reply\n\n:link"],
]);

it('only reminds sent, unanswered links', function (array $attributes) {
    $guest = Guest::factory()->for($this->event)->create();
    $rsvp = Rsvp::factory()->for($guest)->for($this->event)->create($attributes);

    $this->post("/app/rsvps/{$rsvp->id}/remind")
        ->assertSessionHas('error', 'Only sent, unanswered RSVP links can be reminded.');
    expect(Notification::count())->toBe(0);
})->with([
    'pending' => [['status' => RsvpStatus::Pending]],
    'accepted' => [['status' => RsvpStatus::Accepted, 'sent_at' => now(), 'expires_at' => now()->addWeek()]],
    'expired' => [['status' => RsvpStatus::Sent, 'sent_at' => now()->subWeeks(2), 'expires_at' => now()->subDay()]],
]);

it('shows a message with its delivery log', function () {
    $guest = Guest::factory()->for($this->event)->create();
    $notification = Notification::factory()->for($guest)->for($this->event)->create();

    $this->get("/app/events/{$this->event->id}/notifications")->assertOk();
    $this->get("/app/notifications/{$notification->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('client/notifications/show'));
});

it('stops invitations at the per-guest limit, across resends', function () {
    config(['emp.max_invitations_per_guest' => 2]);
    $guest = Guest::factory()->for($this->event)->create(['name' => 'Priya']);

    $this->post("/app/guests/{$guest->id}/rsvps", ['send' => true])->assertSessionHas('success');
    $rsvp = Rsvp::sole();
    $this->post("/app/rsvps/{$rsvp->id}/resend")->assertSessionHas('success');

    $this->post("/app/rsvps/{$rsvp->id}/resend")
        ->assertSessionHas('error', 'Priya has had all 2 invitations for this event.');

    expect(Notification::where('kind', NotificationKind::RsvpInvitation)->count())->toBe(2);
});

it('counts re-invites with a new link toward the same allowance', function () {
    config(['emp.max_invitations_per_guest' => 1]);
    $guest = Guest::factory()->for($this->event)->create(['name' => 'Priya']);
    $this->post("/app/guests/{$guest->id}/rsvps", ['send' => true])->assertSessionHas('success');
    $this->post('/app/rsvps/'.Rsvp::sole()->id.'/expire');

    $this->post("/app/guests/{$guest->id}/rsvps", ['send' => true])
        ->assertSessionHas('error', 'Priya has had all 1 invitation for this event.');

    expect(Notification::count())->toBe(1);
});

it('does not count failed messages', function () {
    config(['emp.max_invitations_per_guest' => 1]);
    $guest = Guest::factory()->for($this->event)->create();
    $rsvp = Rsvp::factory()->for($guest)->for($this->event)->sent()->create();
    Notification::factory()->for($guest)->failed()->create(['kind' => NotificationKind::RsvpInvitation, 'rsvp_id' => $rsvp->id]);

    $this->post("/app/rsvps/{$rsvp->id}/resend")->assertSessionHas('success');
});

it('stops reminders at the per-guest limit', function () {
    config(['emp.max_reminders_per_guest' => 1]);
    $guest = Guest::factory()->for($this->event)->create(['name' => 'Priya']);
    $rsvp = Rsvp::factory()->for($guest)->for($this->event)->sent()->create();

    $this->post("/app/rsvps/{$rsvp->id}/remind")->assertSessionHas('success');
    $this->post("/app/rsvps/{$rsvp->id}/remind")
        ->assertSessionHas('error', 'Priya has had all 1 reminder for this event.');

    expect($rsvp->fresh()->reminder_count)->toBe(1);
});

it('uses the event\'s own limits over the platform default', function () {
    config(['emp.max_reminders_per_guest' => 5]);
    $this->event->update(['max_reminders_per_guest' => 0]);
    $guest = Guest::factory()->for($this->event)->create();
    $rsvp = Rsvp::factory()->for($guest)->for($this->event)->sent()->create();

    $this->post("/app/rsvps/{$rsvp->id}/remind")
        ->assertSessionHas('error', 'No reminders can be sent to guests of this event.');
});

it('shows each guest\'s messages sent against the event\'s limits', function () {
    config(['emp.max_invitations_per_guest' => 3, 'emp.max_reminders_per_guest' => 2]);
    $guest = Guest::factory()->for($this->event)->create();
    Notification::factory()->for($guest)->count(2)->create(['kind' => NotificationKind::RsvpInvitation]);
    Notification::factory()->for($guest)->failed()->create(['kind' => NotificationKind::RsvpInvitation]);
    Notification::factory()->for($guest)->create(['kind' => NotificationKind::RsvpReminder]);

    $this->get("/app/events/{$this->event->id}/guests")->assertInertia(fn (Assert $page) => $page
        ->where('event.data.message_limits', ['invitations' => 3, 'reminders' => 2])
        ->where('guests.data.0.messages_sent', ['invitations' => 2, 'reminders' => 1]));
});
