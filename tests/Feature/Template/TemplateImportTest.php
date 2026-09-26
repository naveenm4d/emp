<?php

use App\Domains\Template\Contracts\TemplateImportServiceInterface;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Enums\TemplateCategory;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Database\Seeders\TemplateSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->package = storage_path('framework/testing/template-package-'.uniqid());
    File::ensureDirectoryExists("{$this->package}/assets/images");
    File::ensureDirectoryExists("{$this->package}/css");

    $this->write = function (array $manifest = [], ?string $markup = null, ?string $styles = null): string {
        File::put("{$this->package}/template.json", json_encode([
            'key' => 'sunset',
            'name' => 'Sunset',
            'version' => '1.0.0',
            'author' => 'EMP Studio',
            'category' => 'party',
            'price' => 69000,
            'display_price' => 'Rs. 690',
            'fonts' => ['https://fonts.googleapis.com/css2?family=Inter'],
            'slot_labels' => ['img_1' => 'Cover'],
            ...$manifest,
        ]));
        File::put("{$this->package}/index.html", $markup ?? '<h1>{{ event.title }}</h1><img src="{{ img_1 }}">{{#if bg_music}}<audio src="{{ bg_music }}"></audio>{{/if}}{{ rsvp }}');
        File::put("{$this->package}/css/style.css", $styles ?? '.x { background: url({{ asset:images/bg.png }}) }');
        File::put("{$this->package}/assets/images/bg.png", 'png');

        return $this->package;
    };

    $this->importer = app(TemplateImportServiceInterface::class);
});

afterEach(fn () => File::deleteDirectory($this->package));

it('imports a package to the template disk and records metadata and slots', function () {
    $version = $this->importer->import(($this->write)());

    $template = Template::sole();
    expect($template)
        ->key->toBe('sunset')
        ->price->toBe(69000)
        ->display_price->toBe('Rs. 690')
        ->latest_version_id->toBe($version->id)
        ->and($version)
        ->path->toBe('templates/party/sunset/1.0.0')
        ->and(array_keys($version->fresh()->getAttributes()))
        ->toEqualCanonicalizing(['id', 'template_id', 'version', 'path', 'checksum', 'published_at', 'created_at', 'updated_at'])
        ->and($version->fonts())->toBe(['https://fonts.googleapis.com/css2?family=Inter'])
        ->and(collect($version->slots())->map(fn ($s) => [$s->key, $s->required, $s->displayLabel()])->all())
        ->toBe([['img_1', true, 'Cover'], ['bg_music', false, 'Background music']]);

    Storage::disk('public')->assertExists([
        'templates/party/sunset/1.0.0/index.html',
        'templates/party/sunset/1.0.0/css/style.css',
        'templates/party/sunset/1.0.0/assets/images/bg.png',
    ]);
});

it('refuses to change an imported version but skips an identical re-import when asked', function () {
    $first = $this->importer->import(($this->write)());

    expect($this->importer->import($this->package, skipUnchanged: true)->id)->toBe($first->id);

    ($this->write)(['slot_labels' => []], '<h1>{{ event.title }}</h1>');

    expect(fn () => $this->importer->import($this->package, skipUnchanged: true))
        ->toThrow('Template versions are immutable');
});

it('publishes a newer version as the latest and keeps the old one', function () {
    $v1 = $this->importer->import(($this->write)());
    $v2 = $this->importer->import(($this->write)(['version' => '1.1.0', 'name' => 'Sunset II']));

    expect(Template::sole())->latest_version_id->toBe($v2->id)->name->toBe('Sunset II')
        ->and(TemplateVersion::count())->toBe(2)
        ->and($v1->fresh())->not->toBeNull();
});

it('rejects packages with problems and leaves nothing behind', function (array $manifest, ?string $markup, string $message) {
    expect(fn () => $this->importer->import(($this->write)($manifest, $markup)))->toThrow($message);

    expect(Template::count())->toBe(0);
    Storage::disk('public')->assertMissing('templates/party/sunset/1.0.0/index.html');
})->with([
    'bad version' => [['version' => 'v1'], null, 'template.json'],
    'bad category' => [['category' => 'rodeo'], null, 'template.json'],
    'unknown label' => [['slot_labels' => ['img_9' => 'Nope']], null, 'slot_labels.img_9'],
    'unsafe markup' => [[], '<img src="{{ img_1 }}" onerror="alert(1)">', 'inline event handler'],
    'missing asset' => [[], '<img src="{{ asset:images/missing.png }}">', 'assets/images/missing.png'],
    'inline script' => [[], '<script>alert(1)</script>', 'index.html contains a <script> tag (put scripts in js/*.js instead).'],
]);

it('stores what the template lets clients edit', function () {
    $version = $this->importer->import(($this->write)(
        ['editable' => [
            'texts' => ['intro' => ['label' => 'Intro', 'default' => 'Hello', 'max' => 40]],
            'colors' => ['accent' => ['label' => 'Accent', 'default' => '#AA3300']],
            'sections' => ['gallery' => ['label' => 'Gallery', 'default' => false]],
        ]],
        '<p>{{ text.intro }}</p><img src="{{ img_1 }}">{{#if section.gallery}}<i>g</i>{{/if}}{{ rsvp }}',
        '.a { color: var(--emp-color-accent) }',
    ));

    expect($version->editable()->toArray())->toBe([
        'texts' => ['intro' => ['label' => 'Intro', 'default' => 'Hello', 'multiline' => false, 'max' => 40]],
        'colors' => ['accent' => ['label' => 'Accent', 'default' => '#aa3300']],
        'sections' => ['gallery' => ['label' => 'Gallery', 'default' => false]],
    ]);
});

it('rejects editable declarations that do not match the markup', function (array $editable, string $markup, string $message) {
    expect(fn () => $this->importer->import(($this->write)(['editable' => $editable], $markup)))->toThrow($message);
})->with([
    'undeclared text' => [[], '<p>{{ text.intro }}</p><img src="{{ img_1 }}">', '{{ text.intro }} is used in index.html but not declared'],
    'unused text' => [['texts' => ['intro' => ['label' => 'Intro', 'default' => 'Hi']]], '<img src="{{ img_1 }}">', 'editable.texts.intro is declared in template.json but not used'],
    'bad colour' => [['colors' => ['accent' => ['label' => 'Accent', 'default' => 'red']]], '<img src="{{ img_1 }}">', 'template.json'],
    'default longer than max' => [['texts' => ['intro' => ['label' => 'Intro', 'default' => 'Too long', 'max' => 3]]], '<p>{{ text.intro }}</p><img src="{{ img_1 }}">', 'longer than its max'],
]);

it('concatenates css files and records js files in path order', function () {
    ($this->write)(styles: '.a { color: red }');
    File::put("{$this->package}/css/theme.css", '.b { color: blue }');
    File::ensureDirectoryExists("{$this->package}/js");
    File::put("{$this->package}/js/b.js", 'b();');
    File::put("{$this->package}/js/a.js", 'a();');

    $version = $this->importer->import($this->package);

    expect($version->stylesheets())->toBe(['css/style.css', 'css/theme.css'])
        ->and($version->scripts())->toBe(['js/a.js', 'js/b.js'])
        ->and($version->styles())->toBe(".a { color: red }\n.b { color: blue }")
        ->and($version->scriptUrls())->toBe([
            Storage::disk('public')->url('templates/party/sunset/1.0.0/js/a.js'),
            Storage::disk('public')->url('templates/party/sunset/1.0.0/js/b.js'),
        ]);
});

it('requires an index.html', function () {
    ($this->write)();
    File::delete("{$this->package}/index.html");

    expect(fn () => $this->importer->import($this->package))->toThrow('The package has no index.html.');
});

it('rejects files outside the fixed structure', function (string $file) {
    ($this->write)();
    File::put("{$this->package}/{$file}", 'x');

    expect(fn () => $this->importer->import($this->package))->toThrow("Unexpected file \"{$file}\"");
})->with([
    'script under assets' => 'assets/evil.js',
    'old styles.css' => 'styles.css',
    'non-css in css/' => 'css/notes.txt',
]);

it('imports from a zip with a top-level folder', function () {
    ($this->write)();
    $zipPath = "{$this->package}.zip";
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE);
    foreach (File::allFiles($this->package) as $file) {
        $zip->addFile($file->getPathname(), 'sunset/'.$file->getRelativePathname());
    }
    $zip->close();

    $this->artisan('templates:import', ['path' => $zipPath])->assertSuccessful();

    expect(Template::sole()->key)->toBe('sunset');
    File::delete($zipPath);
});

it('lists only active templates with a published version', function () {
    $visible = Template::factory()->published()->create();
    Template::factory()->inactive()->published()->create();
    Template::factory()->create();

    expect(app(TemplateQueryServiceInterface::class)->available(null)->pluck('id')->all())
        ->toBe([$visible->id]);
});

it('imports every bundled template package', function () {
    $this->seed(TemplateSeeder::class);

    expect(Template::count())->toBe(13)
        ->and(Template::query()->distinct()->pluck('category')->map->value->sort()->values()->all())
        ->toBe(collect(TemplateCategory::values())->sort()->values()->all())
        ->and(TemplateVersion::all()->filter(fn (TemplateVersion $version) => $version->scripts() !== [])->count())->toBe(5)
        ->and(Template::query()->whereNull('thumbnail_url')->count())->toBe(0);
});
