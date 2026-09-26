<?php

use App\Domains\Client\Models\Client;
use App\Domains\Template\Enums\TemplateType;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Database\Factories\TemplateVersionFactory;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const PREVIEW_MARKUP = '<h1>{{ event.title }}</h1>{{#if event.type}}<p class="type">{{ event.type }}</p>{{/if}}'
    .'<p class="date">{{ event.date }}</p><img src="{{ img_1 }}">{{ rsvp }}';

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->actingAs($this->client, 'client');

    $this->template = Template::factory()->published(PREVIEW_MARKUP)->create(['name' => 'Aurora']);
});

it('lists the templates the client can choose', function () {
    Template::factory()->published()->inactive()->create();
    Template::factory()->published()->create(['type' => TemplateType::Custom, 'client_id' => Client::factory()->create()->id]);
    $own = Template::factory()->published()->create(['type' => TemplateType::Custom, 'client_id' => $this->client->id]);

    $this->get('/app/templates')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/templates/index')
            ->has('templates.data', 2)
            ->where('templates.data', fn ($templates) => collect($templates)->pluck('id')->sort()->values()->all()
                === collect([$this->template->id, $own->id])->sort()->values()->all()));
});

it('previews a template filled with the details typed so far', function () {
    $this->getJson("/app/templates/{$this->template->id}/preview?title=Anna+%26+Raj&event_type=baby_shower&event_date=2027-03-14")
        ->assertOk()
        ->assertJsonPath('id', $this->template->id)
        ->assertJsonPath('name', 'Aurora')
        ->assertJson(fn ($json) => $json
            ->where('html', fn (string $html) => str_contains($html, '<h1>Anna &amp; Raj</h1>')
                && str_contains($html, 'Baby Shower')
                && str_contains($html, 'Sunday, 14 March 2027'))
            ->etc());
});

it('shows sample text for missing or invalid details', function () {
    $this->getJson("/app/templates/{$this->template->id}/preview?event_date=not-a-date")
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('html', fn (string $html) => str_contains($html, '<h1>Anna &amp; Raj</h1>')
                && str_contains($html, 'data:image/svg+xml;base64,')
                && ! str_contains($html, 'not-a-date'))
            ->etc());
});

it('includes the template\'s scripts in the preview', function () {
    $template = Template::factory()->create();
    $version = TemplateVersion::factory()->for($template)->withScript()->create();

    $this->getJson("/app/templates/{$template->id}/preview")
        ->assertOk()
        ->assertJsonPath('scripts', [Storage::disk(config('emp.template_disk'))->url("{$version->path}/js/main.js")]);
});

it('does not preview templates the client cannot choose', function (Closure $template) {
    $this->getJson("/app/templates/{$template()->id}/preview")->assertNotFound();
})->with([
    'inactive' => [fn () => Template::factory()->published()->inactive()->create()],
    'another client\'s custom design' => [fn () => Template::factory()->published()->create([
        'type' => TemplateType::Custom,
        'client_id' => Client::factory()->create()->id,
    ])],
]);

it('preselects a design chosen on the templates page', function () {
    $this->get("/app/events/create?template={$this->template->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selectedTemplateId', $this->template->id));

    $inactive = Template::factory()->published()->inactive()->create();

    $this->get("/app/events/create?template={$inactive->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selectedTemplateId', null));
});

it('fills editable texts and colours with the template\'s defaults in the preview', function () {
    $template = Template::factory()->published('<p>{{ text.intro }}</p><img src="{{ img_1 }}">{{ rsvp }}', '.a { color: var(--emp-color-accent) }')->create();
    TemplateVersionFactory::writeManifest(TemplateVersion::find($template->latest_version_id)->path, ['editable' => [
        'texts' => ['intro' => ['label' => 'Intro', 'default' => 'Welcome friends']],
        'colors' => ['accent' => ['label' => 'Accent', 'default' => '#123456']],
    ]]);

    $this->getJson("/app/templates/{$template->id}/preview")
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('html', fn (string $html) => str_contains($html, '<p>Welcome friends</p>') && str_contains($html, '--emp-color-accent: #123456;'))
            ->etc());
});
