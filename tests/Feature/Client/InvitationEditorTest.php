<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Models\Event;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Database\Factories\TemplateVersionFactory;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const EDITOR_MARKUP = '<p class="intro" title="{{ text.intro }}">{{ text.intro }}</p><h1>{{ event.title }}</h1>'
    .'<img src="{{ img_1 }}">{{#if section.note}}<p class="note">{{ text.note }}</p>{{/if}}{{ rsvp }}';

const EDITOR_SCHEMA = [
    'texts' => [
        'intro' => ['label' => 'Intro', 'default' => 'You are invited', 'max' => 60],
        'note' => ['label' => 'Note', 'default' => 'Dress code: smart', 'multiline' => true, 'max' => 200],
    ],
    'colors' => ['accent' => ['label' => 'Accent', 'default' => '#b08d57']],
    'sections' => ['note' => ['label' => 'Note', 'default' => true]],
];

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->actingAs($this->client->owner, 'client');

    $template = Template::factory()->published(EDITOR_MARKUP, '.intro { color: var(--emp-color-accent) }')->create();
    $this->version = TemplateVersion::find($template->latest_version_id);
    TemplateVersionFactory::writeManifest($this->version->path, ['editable' => EDITOR_SCHEMA]);

    $this->event = Event::factory()->for($this->client)->create(['template_version_id' => $this->version->id]);
    $this->url = "/app/events/{$this->event->id}/design";
    $this->invitation = fn () => Storage::disk('local')->get($this->event->fresh()->rendered_path);
});

it('renders the template\'s editable defaults', function () {
    app(EventDesignServiceInterface::class)->render($this->event);

    expect(($this->invitation)())
        ->toContain('<p class="intro" title="You are invited">You are invited</p>')
        ->toContain('<p class="note">Dress code: smart</p>')
        ->toContain('--emp-color-accent: #b08d57;');
});

it('saves text and colour changes and re-renders the invitation', function () {
    $this->patch($this->url, [
        'texts' => ['intro' => 'Join <us>', 'note' => "Line one\nLine two"],
        'colors' => ['accent' => '#1D4ED8'],
    ])->assertRedirect()->assertSessionHas('success', 'Invitation saved.');

    // jsonb does not keep key order.
    expect($this->event->fresh()->customizations)->toEqualCanonicalizing([
        'texts' => ['intro' => 'Join <us>', 'note' => "Line one\nLine two"],
        'colors' => ['accent' => '#1d4ed8'],
    ])
        ->and(($this->invitation)())
        ->toContain('>Join &lt;us&gt;</p>')
        ->toContain("Line one<br>\nLine two")
        ->toContain('--emp-color-accent: #1d4ed8;');
});

it('previews unsaved changes without storing them or touching the guest invitation', function () {
    app(EventDesignServiceInterface::class)->render($this->event);
    $invitation = ($this->invitation)();

    $this->postJson("{$this->url}/preview", [
        'texts' => ['intro' => 'Draft intro'],
        'colors' => ['accent' => '#112233'],
        'sections' => ['note' => false],
    ])
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('html', fn (string $html) => str_contains($html, '<span data-emp-text="intro">Draft intro</span>')
                && str_contains($html, '--emp-color-accent: #112233;')
                && ! str_contains($html, 'class="note"'))
            ->etc());

    expect($this->event->fresh()->customizations)->toBe([])
        ->and(($this->invitation)())->toBe($invitation);
});

it('validates previewed changes like saved ones', function () {
    $this->postJson("{$this->url}/preview", ['colors' => ['accent' => 'blue']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('colors.accent');
});

it('drops an override that is set back to the default', function () {
    $this->patchJson($this->url, ['texts' => ['intro' => 'Hi']]);
    $this->patchJson($this->url, ['texts' => ['intro' => 'You are invited']]);

    expect($this->event->fresh()->customizations)->toBe([]);
});

it('hides a section the client switches off', function () {
    $this->patch($this->url, ['sections' => ['note' => false]])->assertRedirect();

    expect(($this->invitation)())->not->toContain('class="note"');
});

it('rejects settings the template does not declare and invalid colours', function (array $payload, string $field) {
    $this->patchJson($this->url, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($this->event->fresh()->customizations)->toBe([]);
})->with([
    'unknown text' => [['texts' => ['headline' => 'x']], 'texts'],
    'too long' => [['texts' => ['intro' => str_repeat('a', 61)]], 'texts.intro'],
    'bad colour' => [['colors' => ['accent' => 'blue']], 'colors.accent'],
]);

it('shows the editor with clickable texts and media', function () {
    $this->get($this->url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/events/design')
            ->where('schema.texts.intro.default', 'You are invited')
            ->where('values.sections.note', true)
            ->where('editor.html', fn (string $html) => str_contains($html, '<span data-emp-text="intro">You are invited</span>')
                && str_contains($html, 'title="You are invited"')
                && str_contains($html, '#emp-slot=img_1')));
});

it('keeps matching customisations when the template is upgraded', function () {
    $this->patchJson($this->url, ['texts' => ['intro' => 'Welcome', 'note' => 'Bring a smile']]);

    $v2 = TemplateVersion::factory()->for($this->version->template)
        ->withCode('<p>{{ text.intro }}</p><img src="{{ img_1 }}">{{ rsvp }}')
        ->withManifest(['editable' => ['texts' => ['intro' => ['label' => 'Intro', 'default' => 'Hello']]]])
        ->create(['version' => '2.0.0']);
    $this->version->template->update(['latest_version_id' => $v2->id]);

    $this->post("/app/events/{$this->event->id}/template/upgrade");

    expect(($this->invitation)())->toContain('<p>Welcome</p>')->not->toContain('Bring a smile');
});

it('does not let the client edit a cancelled event or another client\'s event', function () {
    $this->event->update(['state' => EventState::Cancelled]);
    $this->patchJson($this->url, ['texts' => ['intro' => 'x']])->assertStatus(409);

    $other = Event::factory()->create(['template_version_id' => $this->version->id]);
    $this->patchJson("/app/events/{$other->id}/design", ['texts' => ['intro' => 'x']])->assertForbidden();
});

it('switches to another design from the Design tab', function () {
    $other = Template::factory()->published('<p>{{ text.intro }}</p><img src="{{ img_1 }}">{{ rsvp }}')->create(['name' => 'Neon']);
    TemplateVersionFactory::writeManifest(TemplateVersion::find($other->latest_version_id)->path, ['editable' => ['texts' => ['intro' => ['label' => 'Intro', 'default' => 'Hi']]]]);
    $this->patchJson($this->url, ['texts' => ['intro' => 'Welcome']]);

    $this->from($this->url)
        ->patch("{$this->url}/template", ['template_id' => $other->id])
        ->assertRedirect($this->url)
        ->assertSessionHas('success', 'Now using Neon.');

    expect($this->event->fresh()->template_version_id)->toBe($other->latest_version_id)
        ->and(($this->invitation)())->toContain('<p>Welcome</p>');
});

it('does not switch to a design the client cannot use', function () {
    $inactive = Template::factory()->published()->inactive()->create();

    $this->patch("{$this->url}/template", ['template_id' => $inactive->id])
        ->assertSessionHasErrors(['template_id' => 'The selected template is not available.']);

    expect($this->event->fresh()->template_version_id)->toBe($this->version->id);
});
