<?php

use App\Domains\Template\Contracts\TemplateImportServiceInterface;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->package = storage_path('framework/testing/template-package-'.uniqid());
    File::ensureDirectoryExists("{$this->package}/assets/images");

    $this->write = function (array $manifest = [], ?string $markup = null, ?string $styles = null): string {
        File::put("{$this->package}/template.json", json_encode([
            'key' => 'sunset',
            'name' => 'Sunset',
            'version' => '1.0.0',
            'author' => 'EMP Studio',
            'category' => 'party',
            'price' => 19900,
            'display_price' => '₹199',
            'fonts' => ['https://fonts.googleapis.com/css2?family=Inter'],
            'slot_labels' => ['img_1' => 'Cover'],
            ...$manifest,
        ]));
        File::put("{$this->package}/template.html", $markup ?? '<h1>{{ event.title }}</h1><img src="{{ img_1 }}">{{#if bg_music}}<audio src="{{ bg_music }}"></audio>{{/if}}{{ rsvp }}');
        File::put("{$this->package}/styles.css", $styles ?? '.x { background: url({{ asset:images/bg.png }}) }');
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
        ->price->toBe(19900)
        ->display_price->toBe('₹199')
        ->latest_version_id->toBe($version->id)
        ->and($version)
        ->path->toBe('templates/sunset/1.0.0')
        ->fonts->toBe(['https://fonts.googleapis.com/css2?family=Inter'])
        ->and(collect($version->slots())->map(fn ($s) => [$s->key, $s->required, $s->displayLabel()])->all())
        ->toBe([['img_1', true, 'Cover'], ['bg_music', false, 'Background music']]);

    Storage::disk('public')->assertExists([
        'templates/sunset/1.0.0/template.html',
        'templates/sunset/1.0.0/styles.css',
        'templates/sunset/1.0.0/assets/images/bg.png',
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
    Storage::disk('public')->assertMissing('templates/sunset/1.0.0/template.html');
})->with([
    'bad version' => [['version' => 'v1'], null, 'template.json'],
    'bad category' => [['category' => 'rodeo'], null, 'template.json'],
    'unknown label' => [['slot_labels' => ['img_9' => 'Nope']], null, 'slot_labels.img_9'],
    'unsafe markup' => [[], '<img src="{{ img_1 }}" onerror="alert(1)">', 'inline event handler'],
    'missing asset' => [[], '<img src="{{ asset:images/missing.png }}">', 'assets/images/missing.png'],
]);

it('rejects unexpected files', function () {
    ($this->write)();
    File::put("{$this->package}/assets/evil.js", 'alert(1)');

    expect(fn () => $this->importer->import($this->package))->toThrow('Unexpected file "assets/evil.js"');
});

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
