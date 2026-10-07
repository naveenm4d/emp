<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use App\Domains\Template\Enums\MediaType;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const DESIGN_MARKUP = '<h1>{{ event.title }}</h1><img class="hero" src="{{ img_1 }}">'
    .'{{#if img_2}}<img class="extra" src="{{ img_2 }}">{{/if}}'
    .'{{#if bg_music}}<audio data-emp-bg-music src="{{ bg_music }}"></audio>{{/if}}'
    .'{{#if guest.name}}<p>Dear {{ guest.name }}</p>{{/if}}{{ rsvp }}';

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->actingAs($this->client->owner, 'client');

    $this->template = Template::factory()->published(DESIGN_MARKUP)->create();
    $this->event = Event::factory()->for($this->client)->create([
        'template_version_id' => $this->template->latest_version_id,
        'title' => 'Anna & Raj',
    ]);

    $this->renderedHtml = fn () => Storage::disk('local')->get($this->event->fresh()->rendered_path);
});

it('requires a template when creating an event and pins its latest version', function () {
    $this->post('/app/events', ['title' => 'No template'])->assertSessionHasErrors('template_id');

    $this->post('/app/events', ['title' => 'With template', 'template_id' => $this->template->id])->assertRedirect();

    $event = Event::query()->where('title', 'With template')->sole();
    expect($event->template_version_id)->toBe($this->template->latest_version_id)
        ->and($event->rendered_path)->not->toBeNull();
});

it('rejects an inactive template', function () {
    $inactive = Template::factory()->inactive()->published()->create();

    $this->post('/app/events', ['title' => 'X', 'template_id' => $inactive->id])
        ->assertSessionHasErrors(['template_id' => 'The selected template is not available.']);
});

it('shows the design page with the template slots', function () {
    $this->get("/app/events/{$this->event->id}/design")
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/events/design')
            ->where('design.slots.0.key', 'img_1')
            ->where('design.slots.0.required', true)
            ->where('design.slots.1.key', 'img_2')
            ->where('design.slots.2.key', 'bg_music')
            ->where('design.missing', ['Image 1'])
            ->where('design.preview_url', fn (string $url) => str_starts_with($url, url("/preview/{$this->event->id}?expires="))));
});

it('uploads media into a slot and renders its link into the invitation', function () {
    $this->post("/app/events/{$this->event->id}/media", [
        'slot_key' => 'img_1',
        'file' => UploadedFile::fake()->image('cover.jpg'),
    ])->assertRedirect()->assertSessionHas('success');

    $media = EventMedia::sole();
    Storage::disk('public')->assertExists($media->path);

    $directory = "{$this->template->category->value}/{$this->event->id}";

    expect($media->slot_key)->toBe('img_1')
        ->and($media->type)->toBe(MediaType::Image)
        ->and($media->path)->toStartWith("events/{$directory}/media/")
        ->and($this->event->fresh()->rendered_path)->toStartWith("invitations/{$directory}/")
        ->and(($this->renderedHtml)())->toContain('class="hero" src="'.$media->url().'"')
        ->not->toContain('class="extra"')
        ->toContain('{{ guest.name }}');
});

it('replaces the file when a slot is uploaded again', function () {
    $this->post("/app/events/{$this->event->id}/media", ['slot_key' => 'img_1', 'file' => UploadedFile::fake()->image('a.jpg')]);
    $old = EventMedia::sole()->path;

    $this->post("/app/events/{$this->event->id}/media", ['slot_key' => 'img_1', 'file' => UploadedFile::fake()->image('b.jpg')]);

    expect(EventMedia::count())->toBe(1);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists(EventMedia::sole()->path);
});

it('validates uploads against the slot', function (array $payload, string $field) {
    $this->post("/app/events/{$this->event->id}/media", $payload)->assertSessionHasErrors($field);

    expect(EventMedia::count())->toBe(0);
})->with([
    'unknown slot' => [['slot_key' => 'video_1', 'file' => UploadedFile::fake()->create('v.mp4', 10, 'video/mp4')], 'slot_key'],
    'wrong type' => [['slot_key' => 'img_1', 'file' => UploadedFile::fake()->create('song.mp3', 10, 'audio/mpeg')], 'file'],
    'too big' => [['slot_key' => 'img_1', 'file' => UploadedFile::fake()->image('big.jpg')->size(6000)], 'file'],
    'no file' => [['slot_key' => 'img_1'], 'file'],
]);

it('accepts background music in the audio slot', function () {
    $this->post("/app/events/{$this->event->id}/media", [
        'slot_key' => 'bg_music',
        'file' => UploadedFile::fake()->create('song.mp3', 200, 'audio/mpeg'),
    ])->assertSessionHasNoErrors();

    expect(($this->renderedHtml)())->toContain('data-emp-bg-music');
});

it('removes media and re-renders without it', function () {
    $media = EventMedia::factory()->for($this->event)->slot('img_2')->create();
    Storage::disk('public')->put($media->path, 'x');

    $this->delete("/app/events/{$this->event->id}/media/{$media->id}")->assertRedirect();

    expect(EventMedia::count())->toBe(0)->and(($this->renderedHtml)())->not->toContain('class="extra"');
    Storage::disk('public')->assertMissing($media->path);
});

it('forbids changing another client\'s design', function () {
    $other = Event::factory()->create();
    $media = EventMedia::factory()->for($other)->create();

    $this->get("/app/events/{$other->id}/design")->assertForbidden();
    $this->post("/app/events/{$other->id}/media", ['slot_key' => 'img_1', 'file' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
    $this->delete("/app/events/{$other->id}/media/{$media->id}")->assertForbidden();
});

it('does not publish until required media is uploaded', function () {
    $this->from("/app/events/{$this->event->id}")
        ->patch("/app/events/{$this->event->id}/state", ['state' => 'published'])
        ->assertSessionHas('error', 'Upload every required media file before publishing. Missing: Image 1.');

    EventMedia::factory()->for($this->event)->slot('img_1')->create();

    $this->patch("/app/events/{$this->event->id}/state", ['state' => 'published'])->assertSessionHas('success');
});

it('switching template pins the new template and keeps uploads as unused', function () {
    EventMedia::factory()->for($this->event)->slot('img_2')->create();
    $other = Template::factory()->published('<h1>{{ event.title }}</h1>{{ rsvp }}')->create();

    $this->patch("/app/events/{$this->event->id}", ['template_id' => $other->id])->assertRedirect();

    expect($this->event->fresh()->template_version_id)->toBe($other->latest_version_id);

    $this->get("/app/events/{$this->event->id}/design")
        ->assertInertia(fn (Assert $page) => $page
            ->where('design.slots', [])
            ->where('design.unused_media.0.slot_key', 'img_2'));
});

it('keeps the pinned version until the client upgrades', function () {
    $pinned = $this->event->template_version_id;
    $newer = TemplateVersion::factory()->for($this->template)->withCode('<h2>{{ event.title }}</h2>{{ rsvp }}')->create(['version' => '2.0.0']);
    $this->template->update(['latest_version_id' => $newer->id]);

    $this->patch("/app/events/{$this->event->id}", ['title' => 'Renamed', 'template_id' => $this->template->id]);
    expect($this->event->fresh()->template_version_id)->toBe($pinned);

    $this->post("/app/events/{$this->event->id}/template/upgrade")->assertSessionHas('success', 'Now using version 2.0.0 of the template.');

    expect($this->event->fresh()->template_version_id)->toBe($newer->id)
        ->and(($this->renderedHtml)())->toContain('<h2>Renamed</h2>');
});
