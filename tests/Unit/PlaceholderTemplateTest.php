<?php

use App\Domains\Template\Exceptions\InvalidTemplateException;
use App\Domains\Template\Support\MarkupSanitizer;
use App\Domains\Template\Support\PlaceholderContext;
use App\Domains\Template\Support\PlaceholderParser;
use App\Domains\Template\Support\PlaceholderSyntax;
use App\Domains\Template\Support\TemplateRenderer;
use Illuminate\Support\HtmlString;

function render(string $markup, array $values = [], array $defer = []): string
{
    return (new TemplateRenderer)->render(
        PlaceholderSyntax::parse($markup, PlaceholderContext::Markup),
        $values,
        fn (string $path) => "https://cdn.test/assets/{$path}",
        $defer,
    );
}

it('derives media slots from the code, required outside {{#if}} and optional inside', function () {
    $parsed = (new PlaceholderParser)->parse(
        '<img src="{{ img_1 }}">{{#if img_3}}<img src="{{ img_3 }}">{{/if}}{{#if video_1}}<video src="{{ video_1 }}"></video>{{/if}}'
        .'<audio src="{{ bg_music }}"></audio><h1>{{ event.title }}</h1>{{ rsvp }}<img src="{{ asset:images/a.png }}">',
        '.x { background: url({{ asset:bg.webp }}) }',
    );

    expect(collect($parsed->slots)->map(fn ($slot) => [$slot->key, $slot->type->value, $slot->required])->all())->toBe([
        ['img_1', 'image', true],
        ['img_3', 'image', false],
        ['video_1', 'video', false],
        ['bg_music', 'audio', true],
    ])
        ->and($parsed->fields)->toBe(['event.title'])
        ->and($parsed->assets)->toBe(['images/a.png', 'bg.webp'])
        ->and($parsed->hasRsvp)->toBeTrue();
});

it('treats a slot used both inside and outside {{#if}} as required', function () {
    $parsed = (new PlaceholderParser)->parse('{{#if img_1}}<b>{{/if}}<img src="{{ img_1 }}">');

    expect($parsed->slots[0]->required)->toBeTrue();
});

it('rejects invalid template code', function (string $markup, string $message) {
    expect(fn () => (new PlaceholderParser)->parse($markup))
        ->toThrow(InvalidTemplateException::class, $message);
})->with([
    'unknown name' => ['{{ event.secret }}', 'Unknown placeholder'],
    'img_0' => ['{{ img_0 }}', 'Unknown placeholder'],
    'unclosed if' => ['{{#if img_1}}<img>', 'never closed'],
    'stray /if' => ['<p>{{/if}}', 'without a matching'],
    'broken braces' => ['<p>{{ event.title </p>', 'not a complete placeholder'],
    'rsvp in if' => ['{{#if rsvp}}x{{/if}}', 'cannot be used'],
    'bad asset path' => ['{{ asset:../secret.png }}', 'Invalid asset path'],
    'asset not an image' => ['{{ asset:script.js }}', 'Invalid asset path'],
]);

it('parses editable texts and sections', function () {
    $parsed = (new PlaceholderParser)->parse('<p>{{ text.intro }}</p>{{#if section.gallery}}<img src="{{ img_2 }}">{{/if}}');

    expect($parsed->editableKeys('text'))->toBe(['intro'])
        ->and($parsed->editableKeys('section'))->toBe(['gallery'])
        ->and($parsed->slots[0]->required)->toBeFalse();
});

it('only allows sections as {{#if}} blocks', function () {
    expect(fn () => PlaceholderSyntax::parse('<p>{{ section.gallery }}</p>', PlaceholderContext::Markup))
        ->toThrow(InvalidTemplateException::class, '{{ section.gallery }} can only be used as {{#if section.gallery}}');
});

it('tells a decorator whether a placeholder sits inside a tag', function () {
    $seen = [];

    (new TemplateRenderer)->render(
        PlaceholderSyntax::parse('<p title="{{ text.a }}">{{ text.a }}</p>{{#if text.b}}<b>{{ text.b }}</b>{{/if}}', PlaceholderContext::Markup),
        ['text.a' => 'A', 'text.b' => 'B'],
        decorate: function (string $name, string $html, bool $insideTag) use (&$seen) {
            $seen[] = [$name, $insideTag];

            return $html;
        },
    );

    expect($seen)->toBe([['text.a', true], ['text.a', false], ['text.b', false]]);
});

it('only allows assets in styles', function () {
    expect(fn () => (new PlaceholderParser)->parse('<p></p>', '.x { content: "{{ event.title }}" }'))
        ->toThrow(InvalidTemplateException::class, 'CSS (only');
});

it('fills values, escapes text and drops empty optional blocks', function () {
    $html = render(
        '<h1>{{ event.title }}</h1>{{#if img_2}}<img src="{{ img_2 }}">{{/if}}<p>{{ event.description }}</p>{{ rsvp }}<i style="background:url({{ asset:a.png }})"></i>',
        ['event.title' => '<b>Anna & Raj</b> {{ rsvp }}', 'event.description' => new HtmlString('line<br>two')],
    );

    expect($html)->toBe(
        '<h1>&lt;b&gt;Anna &amp; Raj&lt;/b&gt; &#123;&#123; rsvp &#125;&#125;</h1><p>line<br>two</p><div data-emp-rsvp></div>'
        .'<i style="background:url(https://cdn.test/assets/a.png)"></i>',
    );
});

it('leaves deferred guest placeholders for a second pass', function () {
    $first = render('{{#if guest.name}}<p>Dear {{ guest.name }}, {{ event.title }}</p>{{/if}}', ['event.title' => 'Gala'], ['guest.name']);

    expect($first)->toBe('{{#if guest.name}}<p>Dear {{ guest.name }}, Gala</p>{{/if}}');

    $renderer = new TemplateRenderer;
    $second = fn (?string $name) => $renderer->render(PlaceholderSyntax::parse($first, PlaceholderContext::Guest), ['guest.name' => $name]);

    expect($second('Priya <3'))->toBe('<p>Dear Priya &lt;3, Gala</p>')
        ->and($second(null))->toBe('');
});

it('rejects markup that could run script', function (string $markup) {
    expect(fn () => (new MarkupSanitizer)->assertSafeMarkup($markup))->toThrow(InvalidTemplateException::class);
})->with([
    '<script>alert(1)</script>',
    '<img src="x" onerror="alert(1)">',
    '<a href="javascript:alert(1)">x</a>',
    '<a href="javascript&#58;alert(1)">x</a>',
    '<iframe src="https://evil.test"></iframe>',
    '<form action="https://evil.test"><input></form>',
    '<meta http-equiv="refresh" content="0">',
]);

it('rejects unsafe styles', function (string $styles) {
    expect(fn () => (new MarkupSanitizer)->assertSafeStyles($styles))->toThrow(InvalidTemplateException::class);
})->with([
    '@import url(https://evil.test/x.css);',
    '.x { background: url(javascript:alert(1)) }',
    '</style><script>alert(1)</script>',
]);

it('accepts ordinary template markup', function () {
    (new MarkupSanitizer)->assertSafeMarkup('<a class="map" href="{{ event.map_url }}" target="_blank" rel="noopener">Map</a><video src="{{ video_1 }}" controls></video>');
    (new MarkupSanitizer)->assertSafeStyles('.a { color: red } @media (min-width: 1px) { .b { display: none } }');
})->throwsNoExceptions();
