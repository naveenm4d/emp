<?php

use App\Domains\Event\Enums\QuestionType;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\RegistrationQuestion;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Guest\Models\RegistrationAnswer;
use App\Domains\Template\Models\Template;
use Inertia\Testing\AssertableInertia as Assert;

/** The event's public URL path (/{slug}/{code}). */
function publicPath(Event $event): string
{
    return (string) parse_url($event->publicLink->url(), PHP_URL_PATH);
}

it('shows a published event without internal fields', function () {
    $event = Event::factory()->openForRegistration()->create(['slug' => 'summer-gala']);

    $this->get(publicPath($event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('web/events/show')
            ->where('event.id', $event->id)
            ->where('event.registration_open', true)
            ->missing('event.client_id')
            ->missing('event.state')
            ->where('actions.register', url(publicPath($event).'/register')));
});

it('hides draft and cancelled events', function (string $state) {
    $event = Event::factory()->create(['slug' => 'secret', 'state' => $state]);

    $this->get(publicPath($event))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('web/errors/not-found'));
})->with(['draft', 'cancelled']);

it('registers a guest through the public page', function () {
    $event = Event::factory()->openForRegistration()->create();

    $this->from(publicPath($event))
        ->post(publicPath($event).'/register', [
            'name' => 'Ada Lovelace',
            'email' => ' ADA@example.com ',
            'phone' => '+1 (555) 010-0200',
        ])
        ->assertRedirect(publicPath($event))
        ->assertSessionHas('success', "You're registered, Ada Lovelace. See you there!");

    $guest = Guest::sole();
    expect($guest->email)->toBe('ada@example.com')
        ->and($guest->phone)->toBe('+15550100200')
        ->and($guest->source->value)->toBe('public_link')
        ->and($guest->approval_status)->toBe(ApprovalStatus::Approved);
});

it('puts guests in pending when the event requires approval', function () {
    $event = Event::factory()->openForRegistration()->requiresApproval()->create();

    $this->post(publicPath($event).'/register', ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSessionHas('success', "Thanks, Ada! Your registration is awaiting the host's approval.");

    expect(Guest::sole()->approval_status)->toBe(ApprovalStatus::Pending);
});

it('rejects registration when registration is closed', function () {
    $event = Event::factory()->published()->create(['registration_open' => false]);

    $this->post(publicPath($event).'/register', ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSessionHas('error');

    expect(Guest::count())->toBe(0);
});

it('enforces capacity', function () {
    $event = Event::factory()->openForRegistration()->capacity(1)->create();
    Guest::factory()->for($event)->create();

    $this->post(publicPath($event).'/register', ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSessionHas('error');

    expect(Guest::count())->toBe(1);
});

it('does not count rejected or waitlisted guests toward capacity', function () {
    $event = Event::factory()->openForRegistration()->capacity(1)->create();
    Guest::factory()->for($event)->status(ApprovalStatus::Rejected)->create();
    Guest::factory()->for($event)->status(ApprovalStatus::Waitlisted)->create();

    $this->post(publicPath($event).'/register', ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSessionHas('success');
});

it('rejects duplicate email and phone within an event', function (array $payload) {
    $event = Event::factory()->openForRegistration()->create();
    Guest::factory()->for($event)->create(['email' => 'ada@example.com', 'phone' => '+15550100200']);

    $this->post(publicPath($event).'/register', ['name' => 'Ada', ...$payload])
        ->assertSessionHas('error');

    expect(Guest::count())->toBe(1);
})->with([
    'email (case-insensitive)' => [['email' => 'ADA@example.com']],
    'phone (normalised)' => [['phone' => '+1 555 010 0200']],
]);

it('validates the registration form', function () {
    $event = Event::factory()->openForRegistration()->create();

    $this->post(publicPath($event).'/register', [])
        ->assertSessionHasErrors(['name', 'email']);
});

it('shows the event\'s own invitation design, like the guest preview', function () {
    $template = Template::factory()->published('<h1>{{ event.title }}</h1>{{#if guest.name}}<p>Dear {{ guest.name }}</p>{{/if}}{{ rsvp }}')
        ->create(['key' => 'rose']);
    $event = Event::factory()->create([
        'template_version_id' => $template->latest_version_id,
        'state' => 'published',
        'registration_type' => 'open',
        'slug' => 'rose-party',
        'title' => 'Rose Party',
    ]);

    $this->get(publicPath($event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('design.template.key', 'rose')
            ->where('design.html', fn (string $html) => str_contains($html, '<h1>Rose Party</h1>') && ! str_contains($html, 'Dear')));

    $this->get($event->previewUrl())
        ->assertInertia(fn (Assert $page) => $page->where('design.template.key', 'rose'));
});

it('shows an invited-guests-only page for guest-list-only events and refuses registration', function () {
    $event = Event::factory()->published()->create(['slug' => 'private-dinner', 'registration_type' => 'guest_list_only', 'registration_open' => true]);

    $this->get(publicPath($event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('web/events/guest-list-only')
            ->missing('design')
            ->missing('actions'));

    $this->post(publicPath($event).'/register', ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSessionHas('error', 'Registration for this event is closed.');
    expect(Guest::count())->toBe(0);
});

it('registers a guest with the details and answers the event asks for', function () {
    $event = Event::factory()->openForRegistration()->registrationSettings([
        'contact' => ['email' => 'required', 'phone' => 'off', 'company' => 'required', 'job_title' => 'optional', 'address' => 'optional'],
        'party' => ['plus_ones' => true, 'max_additional_guests' => 1],
    ])->create();
    $workshop = RegistrationQuestion::factory()->for($event)->choice(QuestionType::Radio, ['AI', 'Design'])->required()->create();

    $this->post(publicPath($event).'/register', [
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'phone' => '+15550100200',
        'company' => 'Analytical Engines',
        'job_title' => 'Engineer',
        'additional_guests' => 1,
        'answers' => [$workshop->id => 'AI'],
    ])->assertSessionHas('success');

    expect(Guest::sole())
        ->phone->toBeNull()
        ->company->toBe('Analytical Engines')
        ->job_title->toBe('Engineer')
        ->address->toBeNull()
        ->additional_guests->toBe(1)
        ->and(RegistrationAnswer::sole())
        ->question_id->toBe($workshop->id)
        ->value->toBe('AI');
});

it('validates registration against the event settings', function () {
    $event = Event::factory()->openForRegistration()->registrationSettings([
        'contact' => ['email' => 'off', 'phone' => 'required', 'company' => 'required'],
    ])->create();
    $size = RegistrationQuestion::factory()->for($event)->required()->create(['label' => 'Any special requirements?']);

    $this->post(publicPath($event).'/register', ['name' => 'Ada', 'email' => 'ada@example.com'])
        ->assertSessionHasErrors([
            'phone' => 'The phone field is required.',
            'company' => 'The company field is required.',
            "answers.{$size->id}" => 'The Any special requirements? field is required.',
        ]);

    expect(Guest::count())->toBe(0);
});

it('requires the only contact field the event asks for', function () {
    $event = Event::factory()->openForRegistration()->registrationSettings([
        'contact' => ['email' => 'optional', 'phone' => 'off'],
    ])->create();

    $this->post(publicPath($event).'/register', ['name' => 'Ada'])
        ->assertSessionHasErrors(['email' => 'The email field is required.']);
});

it('rejects answers to questions of another event', function () {
    $event = Event::factory()->openForRegistration()->create();
    RegistrationQuestion::factory()->for($event)->create();
    $other = RegistrationQuestion::factory()->create();

    $this->post(publicPath($event).'/register', ['name' => 'Ada', 'email' => 'ada@example.com', 'answers' => [$other->id => 'Hi']])
        ->assertSessionHasErrors('answers');

    expect(Guest::count())->toBe(0);
});

it('gives the public page the registration form', function () {
    $event = Event::factory()->openForRegistration()->registrationSettings(['contact' => ['company' => 'required']])->create();

    $this->get(publicPath($event))->assertInertia(fn (Assert $page) => $page
        ->where('form.contact.email', 'optional')
        ->where('form.contact.company', 'required')
        ->where('form.questions', []));
});
