<?php

namespace Database\Factories;

use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes the version's package to the template disk (faked in tests).
 *
 * @extends Factory<TemplateVersion>
 */
class TemplateVersionFactory extends Factory
{
    protected $model = TemplateVersion::class;

    public const MARKUP = '<article class="card"><h1>{{ event.title }}</h1>'
        .'{{#if guest.name}}<p class="greeting">Dear {{ guest.name }}</p>{{/if}}'
        .'{{#if img_1}}<img src="{{ img_1 }}" alt="">{{/if}}'
        .'<p>{{ event.date }}</p>{{ rsvp }}</article>';

    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'version' => '1.0.0',
            'path' => fn () => self::publish(self::MARKUP),
            'checksum' => fn () => hash('sha256', Str::random(32)),
            'published_at' => now(),
        ];
    }

    public function configure(): static
    {
        // A template's first version becomes its latest.
        return $this->afterCreating(function (TemplateVersion $version) {
            if ($version->template->latest_version_id === null) {
                $version->template->update(['latest_version_id' => $version->id]);
            }
        });
    }

    public function withCode(string $markup, string $styles = ''): static
    {
        return $this->state(fn () => ['path' => self::publish($markup, $styles)]);
    }

    /**
     * Replaces the version's template.json, e.g. to declare fonts, slot_labels or editable.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function withManifest(array $manifest): static
    {
        return $this->afterCreating(fn (TemplateVersion $version) => self::writeManifest($version->path, $manifest));
    }

    /** Adds js/main.js to the version's files. */
    public function withScript(string $code = 'window.empInvitation.root;'): static
    {
        return $this->afterCreating(
            fn (TemplateVersion $version) => Storage::disk((string) config('emp.template_disk'))->put("{$version->path}/js/main.js", $code),
        );
    }

    /**
     * Writes template.json for a version. Call it before the version's files are
     * first read: they are cached forever per id and checksum.
     *
     * @param  array<string, mixed>  $manifest
     */
    public static function writeManifest(string $path, array $manifest): void
    {
        Storage::disk((string) config('emp.template_disk'))->put("{$path}/template.json", (string) json_encode((object) $manifest));
    }

    private static function publish(string $markup, string $styles = ''): string
    {
        $path = 'templates/factory-'.Str::lower(Str::random(10)).'/1.0.0';
        $disk = Storage::disk((string) config('emp.template_disk'));

        self::writeManifest($path, []);
        $disk->put("{$path}/index.html", $markup);
        $disk->put("{$path}/css/style.css", $styles);

        return $path;
    }
}
