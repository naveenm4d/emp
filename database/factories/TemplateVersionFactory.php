<?php

namespace Database\Factories;

use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use App\Domains\Template\Support\PlaceholderParser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes the version's code to the template disk (faked in tests).
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
            'disk' => fn () => config('emp.template_disk'),
            'path' => fn () => self::publish(self::MARKUP),
            'checksum' => fn () => hash('sha256', Str::random(32)),
            'fonts' => [],
            'slot_labels' => [],
            'placeholders' => fn () => app(PlaceholderParser::class)->parse(self::MARKUP)->toArray(),
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
        return $this->state(fn () => [
            'path' => self::publish($markup, $styles),
            'placeholders' => app(PlaceholderParser::class)->parse($markup, $styles)->toArray(),
        ]);
    }

    private static function publish(string $markup, string $styles = ''): string
    {
        $path = 'templates/factory-'.Str::lower(Str::random(10)).'/1.0.0';
        $disk = Storage::disk((string) config('emp.template_disk'));

        $disk->put("{$path}/template.html", $markup);
        $disk->put("{$path}/styles.css", $styles);

        return $path;
    }
}
