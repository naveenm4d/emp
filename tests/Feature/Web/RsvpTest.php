<?php

use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Models\Rsvp;
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
    $this->rsvp = Rsvp::factory()->for($guest)->for($this->event)->sent()->create();
    $this->code = $guest->link->code;
    $this->link = "/{$this->event->slug}/{$this->code}";
});

it('renders the generated invitation with the guest name filled in', function () {
    $route = ['slug' => $this->event->slug, 'code' => $this->code];

    expect($this->rsvp->rsvpUrl())->toBe(url($this->link));

    $this->get($this->link)
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Gala &lt;2026&gt;">', false)
        ->assertSee('<meta property="og:image" content="'.EventMedia::sole()->url().'">', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('web/invitation/show')
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
            ->where('rsvp.status', 'sent')
            ->where('guest.id', $this->rsvp->guest_id)
            ->missing('rsvp.token')
            ->where('actions.accept', route('web.rsvp.accept', $route))
            ->where('actions.decline', route('web.rsvp.decline', $route)));
});

it('sends a strict Content-Security-Policy', function () {
    $csp = $this->get($this->link)->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("script-src 'self' 'nonce-")
        ->toContain("object-src 'none'")
        ->not->toContain("script-src 'self' 'unsafe-inline'");
});

it('does not send the site CSP on the dashboard', function () {
    $this->get('/app/login')->assertHeaderMissing('Content-Security-Policy');
});

it('accepts an RSVP link and confirms the guest RSVP', function () {
    $this->from($this->link)
        ->post("{$this->link}/accept")
        ->assertRedirect($this->link)
        ->assertSessionHas('success', 'Thank you! Your RSVP is confirmed.');

    expect($this->rsvp->fresh()->status)->toBe(RsvpStatus::Accepted)
        ->and($this->rsvp->fresh()->responded_at)->not->toBeNull()
        ->and($this->rsvp->guest->fresh()->rsvp_status)->toBe(GuestRsvpStatus::Confirmed);

    $this->get($this->link)
        ->assertInertia(fn (Assert $page) => $page->where('rsvp.status', 'accepted'));
});

it('declines an RSVP link', function () {
    $this->from($this->link)
        ->post("{$this->link}/decline")
        ->assertRedirect($this->link)
        ->assertSessionHas('success', 'Thanks for letting us know.');

    expect($this->rsvp->guest->fresh()->rsvp_status)->toBe(GuestRsvpStatus::Declined);
});

it('is idempotent once responded', function () {
    $this->post("{$this->link}/accept")->assertSessionHas('success');
    $this->post("{$this->link}/decline")->assertSessionHas('success');

    expect($this->rsvp->fresh()->status)->toBe(RsvpStatus::Accepted);
});

it('tells the guest when the RSVP link expired and marks it expired', function () {
    $rsvp = Rsvp::factory()->expiredYesterday()->create();
    $link = "/{$rsvp->event->slug}/{$rsvp->guest->link->code}";

    $this->from($link)
        ->post("{$link}/accept")
        ->assertRedirect($link)
        ->assertSessionHas('error', 'This RSVP link has expired.');

    expect($rsvp->fresh()->status)->toBe(RsvpStatus::Expired);
});

it('always opens the guest\'s latest RSVP link', function () {
    $this->rsvp->update(['status' => RsvpStatus::Expired]);
    Rsvp::factory()->for($this->rsvp->guest)->for($this->event)->sent()->create(['created_at' => now()->addMinute()]);

    $this->get($this->link)->assertInertia(fn (Assert $page) => $page->where('rsvp.status', 'sent'));
});

it('redirects an outdated slug to the event\'s current slug', function () {
    $this->event->update(['slug' => 'gala-renamed']);

    $this->get("/gala-2025/{$this->code}")->assertStatus(301)->assertRedirect(url("/gala-renamed/{$this->code}"));
});

it('opens the right event when two events share a slug', function () {
    $this->event->update(['slug' => 'john-and-amy']);
    $other = Event::factory()->published()->create(['slug' => 'john-and-amy', 'registration_type' => 'open']);

    $this->get("/john-and-amy/{$this->code}")
        ->assertInertia(fn (Assert $page) => $page->where('event.id', $this->event->id));
    $this->get("/john-and-amy/{$other->publicLink->code}")
        ->assertInertia(fn (Assert $page) => $page->where('event.id', $other->id));
});

it('counts opens by people but not link-preview crawlers', function () {
    $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get($this->link)->assertOk();
    expect($this->rsvp->guest->link->fresh()->open_count)->toBe(0);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')->get($this->link)->assertOk();
    expect($this->rsvp->guest->link->fresh())
        ->open_count->toBe(1)
        ->last_opened_at->not->toBeNull();
});

it('shows the public page to a guest who has no RSVP link yet', function () {
    $this->event->update(['registration_type' => 'open']);
    $guest = Guest::factory()->for($this->event)->create();

    $this->get("/{$this->event->slug}/{$guest->link->code}")
        ->assertInertia(fn (Assert $page) => $page->component('web/events/show'));
});

it('redirects links in older formats', function () {
    $this->get("/rsvp/{$this->rsvp->token}")->assertStatus(301)->assertRedirect(url($this->link));
    $this->get("/e/{$this->event->slug}/{$this->rsvp->guest_id}")->assertStatus(301)->assertRedirect(url($this->link));
    $this->get("/e/{$this->event->slug}")->assertStatus(301)->assertRedirect($this->event->publicLink->url());
});

it('shows not-found for unknown or malformed links and deleted events', function (string $link) {
    $this->get($link)
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('web/errors/not-found'));
})->with([
    'unknown code' => fn () => "/{$this->event->slug}/zzzz2222",
    'malformed code' => fn () => "/{$this->event->slug}/NOT-A-CODE",
    'unknown legacy token' => fn () => '/rsvp/'.str_repeat('a', 64),
    'unknown legacy slug' => fn () => '/e/no-such-event',
    'deleted event' => function () {
        $this->event->delete();

        return $this->link;
    },
]);

it('flashes an error when responding through an unknown or public link', function () {
    $this->post("/{$this->event->slug}/zzzz2222/accept")->assertSessionHas('error');
    $this->post("/{$this->event->slug}/{$this->event->publicLink->code}/accept")->assertSessionHas('error');
});
