<?php

use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use App\Domains\Template\Models\Template;
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
            ->component('site/invitation/show')
            ->where('mode', 'preview')
            ->where('event.id', $this->event->id)
            ->where('design.template.key', 'rose')
            ->where('design.html', fn (string $html) => str_contains($html, 'Dear Guest Name'))
            ->where('invitation', null)
            ->where('actions', null));
});

it('shows link-expired without a valid signature', function (callable $url) {
    $this->get($url($this->event))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('site/errors/link-expired'));
})->with([
    'unsigned' => [fn (Event $event) => "/preview/{$event->id}"],
    'tampered' => [fn (Event $event) => "/preview/{$event->id}?expires=9999999999&signature=bad"],
    'expired' => [fn (Event $event) => URL::temporarySignedRoute('site.preview', now()->subMinute(), ['event' => $event->id])],
]);
