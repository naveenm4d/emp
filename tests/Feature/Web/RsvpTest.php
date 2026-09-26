<?php

use App\Domains\Event\Enums\QuestionType;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use App\Domains\Event\Models\RegistrationQuestion;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Guest\Models\RegistrationAnswer;
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
            ->where('actions.respond', route('web.rsvp.respond', $route)));
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
        ->post("{$this->link}/respond", ['attendance' => 'accepted'])
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
        ->post("{$this->link}/respond", ['attendance' => 'declined'])
        ->assertRedirect($this->link)
        ->assertSessionHas('success', 'Thanks for letting us know.');

    expect($this->rsvp->guest->fresh()->rsvp_status)->toBe(GuestRsvpStatus::Declined);
});

it('keeps the first answer when the event does not allow changes', function () {
    $this->post("{$this->link}/respond", ['attendance' => 'accepted'])->assertSessionHas('success');
    $this->post("{$this->link}/respond", ['attendance' => 'declined'])->assertSessionHas('success');

    expect($this->rsvp->fresh()->status)->toBe(RsvpStatus::Accepted);
});

it('tells the guest when the RSVP link expired and marks it expired', function () {
    $rsvp = Rsvp::factory()->expiredYesterday()->create();
    $link = "/{$rsvp->event->slug}/{$rsvp->guest->link->code}";

    $this->from($link)
        ->post("{$link}/respond", ['attendance' => 'accepted'])
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
    $this->post("/{$this->event->slug}/zzzz2222/respond", ['attendance' => 'accepted'])->assertSessionHas('error');
    $this->post("/{$this->event->slug}/{$this->event->publicLink->code}/respond", ['attendance' => 'accepted'])->assertSessionHas('error');
});

it('stores the details and answers a guest gives when accepting', function () {
    $this->event->update(['event_registration_settings' => [
        'contact' => ['company' => 'required'],
        'party' => ['plus_ones' => true, 'max_additional_guests' => 2, 'children' => true],
        'dietary' => ['enabled' => true, 'options' => ['vegan', 'halal'], 'notes' => true],
    ]]);
    $size = RegistrationQuestion::factory()->for($this->event)->choice(QuestionType::Select, ['S', 'M', 'L'])->required()->create();
    $workshops = RegistrationQuestion::factory()->for($this->event)->choice(QuestionType::MultiSelect, ['AI', 'Design'])->create(['sort_order' => 1]);

    $this->post("{$this->link}/respond", [
        'attendance' => 'accepted',
        'company' => ' Acme ',
        'additional_guests' => 2,
        'children' => 1,
        'dietary_restrictions' => ['vegan'],
        'dietary_notes' => 'No nuts',
        'answers' => [$size->id => 'M', $workshops->id => ['AI', 'Design']],
    ])->assertSessionHas('success');

    expect($this->rsvp->guest->fresh())
        ->rsvp_status->toBe(GuestRsvpStatus::Confirmed)
        ->company->toBe('Acme')
        ->additional_guests->toBe(2)
        ->children->toBe(1)
        ->dietary_restrictions->toBe(['vegan'])
        ->dietary_notes->toBe('No nuts')
        ->and(RegistrationAnswer::where('question_id', $size->id)->sole()->value)->toBe('M')
        ->and(RegistrationAnswer::where('question_id', $workshops->id)->sole()->value)->toBe(['AI', 'Design']);
});

it('validates the details against the event settings', function () {
    $this->event->update(['event_registration_settings' => [
        'contact' => ['company' => 'required'],
        'party' => ['plus_ones' => true, 'max_additional_guests' => 1],
        'dietary' => ['enabled' => true, 'options' => ['vegan']],
    ]]);
    $size = RegistrationQuestion::factory()->for($this->event)->choice(QuestionType::Select, ['S', 'M'])->required()->create(['label' => 'T-shirt size']);

    $this->post("{$this->link}/respond", [
        'attendance' => 'accepted',
        'additional_guests' => 3,
        'dietary_restrictions' => ['halal'],
        'answers' => [$size->id => 'XL'],
    ])->assertSessionHasErrors([
        'company' => 'The company field is required.',
        'additional_guests' => 'The additional guests field must not be greater than 1.',
        'dietary_restrictions.0' => 'The selected dietary_restrictions.0 is invalid.',
        "answers.{$size->id}" => 'The selected T-shirt size is invalid.',
    ]);

    expect($this->rsvp->fresh()->status)->toBe(RsvpStatus::Sent);
});

it('ignores details the event does not ask for', function () {
    $this->post("{$this->link}/respond", ['attendance' => 'accepted', 'company' => 'Acme', 'additional_guests' => 4])
        ->assertSessionHas('success');

    expect($this->rsvp->guest->fresh())
        ->company->toBeNull()
        ->additional_guests->toBe(0);
});

it('asks nothing else when declining', function () {
    $this->event->update(['event_registration_settings' => ['contact' => ['company' => 'required']]]);

    $this->post("{$this->link}/respond", ['attendance' => 'declined', 'company' => 'Acme'])
        ->assertSessionHas('success', 'Thanks for letting us know.');

    expect($this->rsvp->guest->fresh())
        ->rsvp_status->toBe(GuestRsvpStatus::Declined)
        ->company->toBeNull();
});

it('accepts maybe only when the event allows it', function () {
    $this->post("{$this->link}/respond", ['attendance' => 'maybe'])
        ->assertSessionHasErrors(['attendance' => 'Choose whether you will attend.']);

    $this->event->update(['event_registration_settings' => ['attendance' => ['allow_maybe' => true]]]);

    $this->post("{$this->link}/respond", ['attendance' => 'maybe'])
        ->assertSessionHas('success', "Thanks! We've noted that you might come.");

    expect($this->rsvp->fresh()->status)->toBe(RsvpStatus::Maybe)
        ->and($this->rsvp->guest->fresh()->rsvp_status)->toBe(GuestRsvpStatus::Maybe);
});

it('lets guests change their answer until the lock time when the event allows it', function () {
    $this->event->update([
        'event_registration_settings' => ['responses' => ['editable' => true]],
        'responses_lock_at' => now()->addDay(),
    ]);

    $this->post("{$this->link}/respond", ['attendance' => 'accepted'])->assertSessionHas('success');
    $this->get($this->link)->assertInertia(fn (Assert $page) => $page->where('rsvp.can_change', true));

    $this->post("{$this->link}/respond", ['attendance' => 'declined'])->assertSessionHas('success');
    expect($this->rsvp->fresh()->status)->toBe(RsvpStatus::Declined)
        ->and($this->rsvp->guest->fresh()->rsvp_status)->toBe(GuestRsvpStatus::Declined);

    $this->travelTo(now()->addDays(2));

    $this->post("{$this->link}/respond", ['attendance' => 'accepted'])->assertSessionHas('success');
    expect($this->rsvp->fresh()->status)->toBe(RsvpStatus::Declined);
    $this->get($this->link)->assertInertia(fn (Assert $page) => $page->where('rsvp.can_change', false));
});

it('keeps answers final once the event has started', function () {
    $this->travelTo(now()->setTime(12, 0));
    $this->event->update([
        'event_registration_settings' => ['responses' => ['editable' => true]],
        'event_date' => now()->toDateString(),
        'start_time' => now()->subHour()->format('H:i'),
    ]);
    $this->rsvp->update(['status' => RsvpStatus::Accepted, 'responded_at' => now()->subDay()]);

    $this->post("{$this->link}/respond", ['attendance' => 'declined'])->assertSessionHas('success');

    expect($this->rsvp->fresh()->status)->toBe(RsvpStatus::Accepted);
});

it('fills the form with what the guest already answered', function () {
    $this->event->update(['event_registration_settings' => ['contact' => ['company' => 'optional']]]);
    $question = RegistrationQuestion::factory()->for($this->event)->create();
    $guest = $this->rsvp->guest;
    $guest->update(['company' => 'Acme']);
    RegistrationAnswer::factory()->for($guest)->for($question, 'question')->create(['value' => 'Wheelchair access']);

    $this->get($this->link)->assertInertia(fn (Assert $page) => $page
        ->where('form.contact.company', 'optional')
        ->where('form.contact.email', 'off')
        ->where('form.questions.0.id', $question->id)
        ->where('response.company', 'Acme')
        ->where("response.answers.{$question->id}", 'Wheelchair access'));
});

it('lets guests check and correct the phone the client entered', function () {
    $this->event->update(['event_registration_settings' => ['contact' => ['phone' => 'optional', 'email' => 'off']]]);
    $guest = $this->rsvp->guest;

    $this->get($this->link)->assertInertia(fn (Assert $page) => $page
        ->where('form.contact.phone', 'optional')
        ->where('response.phone', $guest->phone));

    $this->post("{$this->link}/respond", ['attendance' => 'accepted', 'phone' => '+1 555 010 0999'])
        ->assertSessionHas('success');

    expect($guest->fresh()->phone)->toBe('+15550100999');
});

it('refuses removing the guest\'s only contact', function () {
    $this->event->update(['event_registration_settings' => ['contact' => ['phone' => 'optional']]]);
    $this->rsvp->guest->update(['email' => null]);

    $this->post("{$this->link}/respond", ['attendance' => 'accepted', 'phone' => ''])
        ->assertSessionHasErrors(['phone' => 'Add a phone number or an email.']);

    expect($this->rsvp->fresh()->status)->toBe(RsvpStatus::Sent);
});

it('refuses a phone number another guest already has', function () {
    $this->event->update(['event_registration_settings' => ['contact' => ['phone' => 'optional']]]);
    Guest::factory()->for($this->event)->create(['phone' => '+15550100777']);

    $this->post("{$this->link}/respond", ['attendance' => 'accepted', 'phone' => '+15550100777'])
        ->assertSessionHas('error', 'A guest with this phone number is already registered for this event.');

    expect($this->rsvp->fresh()->status)->toBe(RsvpStatus::Sent);
});

it('lets a guest bring up to the plus-ones they were invited with', function () {
    $this->rsvp->guest->update(['invited_additional_guests' => 1]);

    $this->get($this->link)->assertInertia(fn (Assert $page) => $page
        ->where('form.plus_ones', true)
        ->where('form.max_additional_guests', 1));

    $this->post("{$this->link}/respond", ['attendance' => 'accepted', 'additional_guests' => 2])
        ->assertSessionHasErrors(['additional_guests' => 'The additional guests field must not be greater than 1.']);

    $this->post("{$this->link}/respond", ['attendance' => 'accepted', 'additional_guests' => 1])
        ->assertSessionHas('success');

    expect($this->rsvp->guest->fresh()->additional_guests)->toBe(1);
});

it('asks no plus-ones of a guest invited without any', function () {
    $this->event->update(['event_registration_settings' => ['party' => ['plus_ones' => true, 'max_additional_guests' => 3]]]);
    $this->rsvp->guest->update(['invited_additional_guests' => 0]);

    $this->post("{$this->link}/respond", ['attendance' => 'accepted', 'additional_guests' => 2])
        ->assertSessionHas('success');

    expect($this->rsvp->guest->fresh()->additional_guests)->toBe(0);
    $this->get($this->link)->assertInertia(fn (Assert $page) => $page->where('form.plus_ones', false));
});

it('keeps the guest\'s note to the host when declining', function () {
    $this->post("{$this->link}/respond", ['attendance' => 'declined', 'note' => ' Travelling that week, sorry! '])
        ->assertSessionHas('success');

    expect($this->rsvp->fresh()->response_note)->toBe('Travelling that week, sorry!');
});

it('drops the decline note when the guest changes to attending', function () {
    $this->event->update(['event_registration_settings' => ['responses' => ['editable' => true]]]);

    $this->post("{$this->link}/respond", ['attendance' => 'declined', 'note' => 'Away'])->assertSessionHas('success');
    $this->post("{$this->link}/respond", ['attendance' => 'accepted', 'note' => 'Ignored'])->assertSessionHas('success');

    expect($this->rsvp->fresh())
        ->status->toBe(RsvpStatus::Accepted)
        ->response_note->toBeNull();
});

it('limits the decline note length', function () {
    $this->post("{$this->link}/respond", ['attendance' => 'declined', 'note' => str_repeat('a', 501)])
        ->assertSessionHasErrors(['note' => 'The note field must not be greater than 500 characters.']);
});
