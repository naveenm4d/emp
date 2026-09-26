<?php

namespace App\Domains\Template\Models;

use App\Domains\Event\Models\Event;
use App\Domains\Template\DTOs\EditableSchema;
use App\Domains\Template\DTOs\MediaSlot;
use App\Domains\Template\DTOs\ParsedTemplate;
use App\Domains\Template\Support\PlaceholderParser;
use Carbon\CarbonImmutable;
use Closure;
use Database\Factories\TemplateVersionFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * One immutable version of a template's code. The row only points at the
 * package (template.json, index.html, css/, js/, assets/) on the template disk
 * under $path; everything else is read from those files and cached forever
 * per id and checksum.
 *
 * @property string $id
 * @property string $template_id
 * @property string $version semver
 * @property string $path e.g. templates/wedding/floral/1.0.0
 * @property string $checksum sha256 of the package
 * @property CarbonImmutable $published_at
 * @property-read Template $template
 * @property-read int|null $events_count
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(TemplateVersionFactory::class)]
#[Fillable(['template_id', 'version', 'path', 'checksum', 'published_at'])]
class TemplateVersion extends Model
{
    /** @use HasFactory<TemplateVersionFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /** @return array<string, mixed> the package's template.json (validated on import) */
    public function manifest(): array
    {
        return (array) json_decode($this->file('template.json'), true);
    }

    /** @return list<string> font stylesheet URLs from template.json */
    public function fonts(): array
    {
        return array_values($this->manifest()['fonts'] ?? []);
    }

    /** What clients may change, as declared in template.json. */
    public function editable(): EditableSchema
    {
        return EditableSchema::fromArray($this->manifest()['editable'] ?? []);
    }

    /** The placeholders used by index.html and the css/ files. */
    public function parsed(): ParsedTemplate
    {
        return ParsedTemplate::fromArray($this->remember('parsed', fn () => app(PlaceholderParser::class)->parse($this->markup(), $this->styles())->toArray()));
    }

    /** @return list<MediaSlot> the media placeholders in this version's code, with labels */
    public function slots(): array
    {
        $labels = $this->manifest()['slot_labels'] ?? [];

        return array_map(
            fn (MediaSlot $slot) => $slot->withLabel($labels[$slot->key] ?? null),
            $this->parsed()->slots,
        );
    }

    public function slot(string $key): ?MediaSlot
    {
        foreach ($this->slots() as $slot) {
            if ($slot->key === $key) {
                return $slot;
            }
        }

        return null;
    }

    public function markup(): string
    {
        return $this->file('index.html');
    }

    /** The version's css/ files concatenated in path order. */
    public function styles(): string
    {
        return implode("\n", array_map($this->file(...), $this->stylesheets()));
    }

    /** @return list<string> css/ files, relative to $path, in the order they are concatenated */
    public function stylesheets(): array
    {
        return $this->filesIn('css', 'css');
    }

    /** @return list<string> js/ files, relative to $path, in the order they run */
    public function scripts(): array
    {
        return $this->filesIn('js', 'js');
    }

    /** @return list<string> public URLs of the version's js/ files, in run order */
    public function scriptUrls(): array
    {
        return array_map(fn (string $script) => $this->disk()->url("{$this->path}/{$script}"), $this->scripts());
    }

    public function assetUrl(string $path): string
    {
        return $this->disk()->url("{$this->path}/assets/{$path}");
    }

    /** @return list<string> the files with $extension in the package's $folder, relative to $path, in path order */
    private function filesIn(string $folder, string $extension): array
    {
        return $this->remember("{$folder}/", function () use ($folder, $extension) {
            $files = array_values(array_filter(
                array_map(fn (string $file) => substr($file, strlen("{$this->path}/")), $this->disk()->files("{$this->path}/{$folder}")),
                fn (string $file) => strtolower(pathinfo($file, PATHINFO_EXTENSION)) === $extension,
            ));

            sort($files);

            return $files;
        });
    }

    private function file(string $name): string
    {
        return $this->remember($name, function () use ($name) {
            $contents = $this->disk()->get("{$this->path}/{$name}");

            if ($contents === null) {
                throw new RuntimeException("Template file {$this->path}/{$name} is missing on the template disk.");
            }

            return $contents;
        });
    }

    /**
     * Versions are immutable, so anything read or derived from their files can be cached forever.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    private function remember(string $key, Closure $callback): mixed
    {
        return Cache::rememberForever("template:{$this->id}:{$this->checksum}:{$key}", $callback);
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('emp.template_disk'));
    }

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** @return HasMany<Event, $this> events designed with (pinned to) this version */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
