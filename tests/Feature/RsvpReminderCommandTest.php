<?php

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Models\Notification;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Models\Rsvp;

beforeEach(function () {
    config(['services.whatsapp.api_token' => null]);
    $this->travelTo(now()->startOfDay()->setHour(10));

    $this->event = Event::factory()->published()->create([
        'auto_reminders' => true,
        'remind_after_days' => 3,
        'remind_before_days' => 2,
        'event_date' => now()->addDays(30)->toDateString(),
    ]);
    $this->guest = Guest::factory()->for($this->event)->create();
});

/** A link sent $daysAgo days ago that expires well after the test window. */
function sentLink(Event $event, Guest $guest, int $daysAgo, array $attributes = []): Rsvp
{
    return Rsvp::factory()->for($guest)->for($event)->create([
        'status' => RsvpStatus::Sent,
        'sent_at' => now()->subDays($daysAgo),
        'expires_at' => now()->addDays(60),
        ...$attributes,
    ]);
}

it('reminds once, remind_after_days after the link was sent', function () {
    $rsvp = sentLink($this->event, $this->guest, 3);
    $early = sentLink($this->event, Guest::factory()->for($this->event)->create(), 2);

    $this->artisan('rsvps:send-reminders')->assertSuccessful();

    expect($rsvp->fresh())->reminder_count->toBe(1)->auto_after_reminded_at->not->toBeNull()
        ->and($early->fresh()->reminder_count)->toBe(0)
        ->and(Notification::count())->toBe(1);

    $this->travelTo(now()->addDays(2));
    $this->artisan('rsvps:send-reminders');

    expect($rsvp->fresh()->reminder_count)->toBe(1);
});

it('reminds once in the days before the event', function () {
    $this->event->update(['event_date' => now()->addDays(2)->toDateString(), 'remind_after_days' => 30]);
    $rsvp = sentLink($this->event, $this->guest, 5);

    $this->artisan('rsvps:send-reminders');
    $this->travelTo(now()->addDay()->addHour());
    $this->artisan('rsvps:send-reminders');

    expect($rsvp->fresh())->reminder_count->toBe(1)->auto_before_reminded_at->not->toBeNull();
});

it('sends one message when both reminders are due', function () {
    $this->event->update(['event_date' => now()->addDay()->toDateString()]);
    $rsvp = sentLink($this->event, $this->guest, 4);

    $this->artisan('rsvps:send-reminders');

    expect($rsvp->fresh())
        ->reminder_count->toBe(1)
        ->auto_after_reminded_at->not->toBeNull()
        ->auto_before_reminded_at->not->toBeNull()
        ->and(Notification::count())->toBe(1);
});

it('never reminds twice within a day', function () {
    $this->event->update(['event_date' => now()->addDay()->toDateString(), 'remind_after_days' => 30]);
    sentLink($this->event, $this->guest, 5, ['reminder_count' => 1, 'last_reminded_at' => now()->subHours(5)]);
    sentLink($this->event, Guest::factory()->for($this->event)->create(), 0);

    $this->artisan('rsvps:send-reminders');

    expect(Notification::count())->toBe(0);
});

it('skips events without automatic reminders, unpublished events and answered links', function () {
    $off = Event::factory()->published()->create(['auto_reminders' => false]);
    sentLink($off, Guest::factory()->for($off)->create(), 5);

    $draft = Event::factory()->create(['auto_reminders' => true]);
    sentLink($draft, Guest::factory()->for($draft)->create(), 5);

    sentLink($this->event, $this->guest, 5, ['status' => RsvpStatus::Accepted]);

    $this->artisan('rsvps:send-reminders');

    expect(Notification::count())->toBe(0);
});
