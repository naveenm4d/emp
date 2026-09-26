<?php

use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $template = Template::factory()->published(
        '<h1>{{ event.title }}</h1><img src="{{ img_1 }}">{{#if guest.name}}<p>Dear {{ guest.name }}</p>{{/if}}{{ rsvp }}',
        '.x { color: red }',
    )->create(['key' => 'rose', 'name' => 'Rose']);

    $this->event = Event::factory()->create(['template_version_id' => $template->latest_version_id, 'state' => 'draft']);
    EventMedia::factory()->for($this->event)->create();
});

it('serves a preview through a valid signed link, even for drafts', function () {
    $this->get($this->event->previewUrl())
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page
            ->component('web/invitation/show')
            ->where('mode', 'preview')
            ->where('event.id', $this->event->id)
            ->where('design.template.key', 'rose')
            ->where('design.html', fn (string $html) => str_contains($html, 'Dear Guest Name'))
            ->where('rsvp', null)
            ->where('actions', null));
});

it('passes the template\'s scripts to the invitation page', function () {
    $template = Template::factory()->create();
    $version = TemplateVersion::factory()->for($template)->withScript()->create();
    $event = Event::factory()->create(['template_version_id' => $version->id]);
    EventMedia::factory()->for($event)->create();

    $this->get($event->previewUrl())
        ->assertInertia(fn (Assert $page) => $page
            ->where('design.scripts', [Storage::disk(config('emp.template_disk'))->url("{$version->path}/js/main.js")]));
});

it('shows link-expired without a valid signature', function (callable $url) {
    $this->get($url($this->event))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('web/errors/link-expired'));
})->with([
    'unsigned' => [fn (Event $event) => "/preview/{$event->id}"],
    'tampered' => [fn (Event $event) => "/preview/{$event->id}?expires=9999999999&signature=bad"],
    'expired' => [fn (Event $event) => URL::temporarySignedRoute('web.preview', now()->subMinute(), ['event' => $event->id])],
]);
