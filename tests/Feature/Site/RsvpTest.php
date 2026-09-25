<?php

use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use App\Domains\Guest\Enums\RsvpStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Invitation\Enums\InvitationStatus;
use App\Domains\Invitation\Models\Invitation;
use App\Domains\Template\Models\Template;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $template = Template::factory()->published(
        '<h1>{{ event.title }}</h1><img src="{{ img_1 }}">{{#if guest.name}}<p>Dear {{ guest.name }}</p>{{/if}}{{ rsvp }}',
        '.x { color: red }',
    )->create(['key' => 'rose', 'name' => 'Rose']);

    $this->event = Event::factory()->published()->create([
        'template_version_id' => $template->latest_version_id,
        'title' => 'Gala <2026>',
        'description' => 'Join us.',
    ]);
    EventMedia::factory()->for($this->event)->create();

    $guest = Guest::factory()->for($this->event)->create(['name' => 'Priya {{ rsvp }}']);
    $this->invitation = Invitation::factory()->for($guest)->for($this->event)->sent()->create();
});

it('renders the generated invitation with the guest name filled in', function () {
    $token = $this->invitation->token;

    $this->get("/rsvp/{$token}")
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Gala &lt;2026&gt;">', false)
        ->assertSee('<meta property="og:image" content="'.EventMedia::sole()->url().'">', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('site/invitation/show')
            ->where('mode', 'rsvp')
            ->where('event.id', $this->event->id)
            ->where('design.template', ['key' => 'rose', 'name' => 'Rose', 'version' => '1.0.0'])
            ->where('design.has_bg_music', false)
            ->where('design.cover_image_url', EventMedia::sole()->url())
            ->where('design.html', fn (string $html) => str_contains($html, '<style>')
                && str_contains($html, '<h1>Gala &lt;2026&gt;</h1>')
                && str_contains($html, '<p>Dear Priya &#123;&#123; rsvp &#125;&#125;</p>')
                && str_contains($html, '<div data-emp-rsvp></div>')
                && ! str_contains($html, '{{'))
            ->where('invitation.status', 'sent')
            ->where('guest.id', $this->invitation->guest_id)
            ->missing('invitation.token')
            ->where('actions.accept', route('site.rsvp.accept', $token))
            ->where('actions.decline', route('site.rsvp.decline', $token)));
});

it('sends a strict Content-Security-Policy', function () {
    $csp = $this->get("/rsvp/{$this->invitation->token}")->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("script-src 'self' 'nonce-")
        ->toContain("object-src 'none'")
        ->not->toContain("script-src 'self' 'unsafe-inline'");
});

it('does not send the site CSP on the dashboard', function () {
    $this->get('/app/login')->assertHeaderMissing('Content-Security-Policy');
});

it('accepts an invitation and confirms the guest RSVP', function () {
    $token = $this->invitation->token;

    $this->from("/rsvp/{$token}")
        ->post("/rsvp/{$token}/accept")
        ->assertRedirect("/rsvp/{$token}")
        ->assertSessionHas('success', 'Thank you! Your RSVP is confirmed.');

    expect($this->invitation->fresh()->status)->toBe(InvitationStatus::Accepted)
        ->and($this->invitation->fresh()->responded_at)->not->toBeNull()
        ->and($this->invitation->guest->fresh()->rsvp_status)->toBe(RsvpStatus::Confirmed);

    $this->get("/rsvp/{$token}")
        ->assertInertia(fn (Assert $page) => $page->where('invitation.status', 'accepted'));
});

it('declines an invitation', function () {
    $token = $this->invitation->token;

    $this->from("/rsvp/{$token}")
        ->post("/rsvp/{$token}/decline")
        ->assertRedirect("/rsvp/{$token}")
        ->assertSessionHas('success', 'Thanks for letting us know.');

    expect($this->invitation->guest->fresh()->rsvp_status)->toBe(RsvpStatus::Declined);
});

it('is idempotent once responded', function () {
    $token = $this->invitation->token;

    $this->post("/rsvp/{$token}/accept")->assertSessionHas('success');
    $this->post("/rsvp/{$token}/decline")->assertSessionHas('success');

    expect($this->invitation->fresh()->status)->toBe(InvitationStatus::Accepted);
});

it('tells the guest when the invitation expired and marks it expired', function () {
    $invitation = Invitation::factory()->expiredYesterday()->create();

    $this->from("/rsvp/{$invitation->token}")
        ->post("/rsvp/{$invitation->token}/accept")
        ->assertRedirect("/rsvp/{$invitation->token}")
        ->assertSessionHas('error', 'This invitation has expired.');

    expect($invitation->fresh()->status)->toBe(InvitationStatus::Expired);
});

it('shows not-found for an unknown or malformed token', function (string $token) {
    $this->get("/rsvp/{$token}")
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('site/errors/not-found'));
})->with([
    'unknown' => [str_repeat('a', 64)],
    'malformed' => ['not-a-token'],
]);

it('flashes an error when responding to an unknown token', function () {
    $this->post('/rsvp/'.str_repeat('a', 64).'/accept')->assertSessionHas('error');
});
