<?php

namespace App\Domains\Template\Models;

use App\Domains\Template\DTOs\MediaSlot;
use App\Domains\Template\DTOs\ParsedTemplate;
use Carbon\CarbonImmutable;
use Database\Factories\TemplateVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * One immutable version of a template's code. The files (template.html,
 * styles.css, template.json, assets/) live on $disk under $path.
 *
 * @property string $id
 * @property string $template_id
 * @property string $version semver
 * @property string $disk
 * @property string $path e.g. templates/floral/1.0.0
 * @property string $checksum sha256 of the package
 * @property list<string> $fonts stylesheet URLs
 * @property array<string, string> $slot_labels
 * @property array{slots?: list<array{key: string, type?: string, required?: bool}>, fields?: list<string>, assets?: list<string>, has_rsvp?: bool} $placeholders
 * @property CarbonImmutable $published_at
 * @property-read Template $template
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(TemplateVersionFactory::class)]
#[Fillable(['template_id', 'version', 'disk', 'path', 'checksum', 'fonts', 'slot_labels', 'placeholders', 'published_at'])]
class TemplateVersion extends Model
{
    /** @use HasFactory<TemplateVersionFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fonts' => 'array',
            'slot_labels' => 'array',
            'placeholders' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function parsed(): ParsedTemplate
    {
        return ParsedTemplate::fromArray($this->placeholders);
    }

    /** @return list<MediaSlot> the media placeholders in this version's code, with labels */
    public function slots(): array
    {
        return array_map(
            fn (MediaSlot $slot) => $slot->withLabel($this->slot_labels[$slot->key] ?? null),
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
        return $this->file('template.html');
    }

    public function styles(): string
    {
        return $this->file('styles.css');
    }

    public function assetUrl(string $path): string
    {
        return Storage::disk($this->disk)->url("{$this->path}/assets/{$path}");
    }

    /** Versions are immutable, so their files can be cached forever. */
    private function file(string $name): string
    {
        return Cache::rememberForever("template:{$this->id}:{$this->checksum}:{$name}", function () use ($name) {
            $contents = Storage::disk($this->disk)->get("{$this->path}/{$name}");

            if ($contents === null) {
                throw new RuntimeException("Template file {$this->path}/{$name} is missing on disk [{$this->disk}].");
            }

            return $contents;
        });
    }

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
