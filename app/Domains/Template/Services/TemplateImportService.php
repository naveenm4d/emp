<?php

namespace App\Domains\Template\Services;

use App\Core\Services\BaseService;
use App\Domains\Template\Contracts\TemplateImportServiceInterface;
use App\Domains\Template\Contracts\TemplateRepositoryInterface;
use App\Domains\Template\Enums\TemplateCategory;
use App\Domains\Template\Enums\TemplateType;
use App\Domains\Template\Exceptions\InvalidTemplateException;
use App\Domains\Template\Exceptions\TemplateVersionExistsException;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use App\Domains\Template\Support\MarkupSanitizer;
use App\Domains\Template\Support\PlaceholderParser;
use App\Domains\Template\Support\PlaceholderSyntax;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;
use ZipArchive;

/**
 * Validates a template package and publishes it to the template disk:
 *
 *   template.json   metadata (key, name, version, author, category, price, …)
 *   template.html   markup with placeholders
 *   styles.css      optional
 *   thumbnail.webp  optional (png/jpg/webp)
 *   assets/…        optional images the code references with {{ asset:… }}
 */
class TemplateImportService extends BaseService implements TemplateImportServiceInterface
{
    private const THUMBNAILS = ['thumbnail.webp', 'thumbnail.png', 'thumbnail.jpg', 'thumbnail.jpeg'];

    public function __construct(
        private readonly TemplateRepositoryInterface $templates,
        private readonly PlaceholderParser $parser,
        private readonly MarkupSanitizer $sanitizer,
    ) {}

    public function import(string $source, bool $skipUnchanged = false): TemplateVersion
    {
        [$directory, $temporary] = $this->unpack($source);

        try {
            return $this->importDirectory($directory, $skipUnchanged);
        } finally {
            if ($temporary) {
                File::deleteDirectory($temporary);
            }
        }
    }

    private function importDirectory(string $directory, bool $skipUnchanged): TemplateVersion
    {
        $manifest = $this->manifest($directory);
        $files = $this->packageFiles($directory);

        if (! isset($files['template.html'])) {
            throw new InvalidTemplateException('The package has no template.html.');
        }

        $markup = (string) file_get_contents($files['template.html']);
        $styles = isset($files['styles.css']) ? (string) file_get_contents($files['styles.css']) : '';

        $this->sanitizer->assertSafeMarkup($markup);
        $this->sanitizer->assertSafeStyles($styles);
        $parsed = $this->parser->parse($markup, $styles);

        foreach ($parsed->assets as $asset) {
            if (! isset($files["assets/{$asset}"])) {
                throw new InvalidTemplateException("{{ asset:{$asset} }} is used but assets/{$asset} is not in the package.");
            }
        }

        $slotKeys = array_map(fn ($slot) => $slot->key, $parsed->slots);

        foreach (array_keys($manifest['slot_labels']) as $key) {
            if (! in_array($key, $slotKeys, true)) {
                throw new InvalidTemplateException("slot_labels.{$key} does not match any media placeholder in template.html.");
            }
        }

        $checksum = $this->checksum($files);
        $template = $this->templates->findByKey($manifest['key']);

        if ($template && ($existing = $this->templates->findVersion($template->id, $manifest['version']))) {
            if ($skipUnchanged && $existing->checksum === $checksum) {
                return $existing;
            }

            throw $existing->checksum === $checksum
                ? new TemplateVersionExistsException("{$manifest['key']} {$manifest['version']} is already imported.")
                : new TemplateVersionExistsException;
        }

        $disk = (string) config('emp.template_disk');
        $path = "templates/{$manifest['key']}/{$manifest['version']}";

        // Leftovers from an earlier failed import have no database row.
        Storage::disk($disk)->deleteDirectory($path);

        foreach ($files as $relative => $absolute) {
            Storage::disk($disk)->put("{$path}/{$relative}", (string) file_get_contents($absolute));
        }

        if (! isset($files['styles.css'])) {
            Storage::disk($disk)->put("{$path}/styles.css", '');
        }

        try {
            return $this->transaction(function () use ($template, $manifest, $disk, $path, $checksum, $parsed, $files) {
                $template ??= $this->templates->create([
                    ...$this->catalogueAttributes($manifest),
                    'key' => $manifest['key'],
                    'type' => TemplateType::Predefined,
                ]);

                /** @var Template $template */
                $version = $this->templates->createVersion([
                    'template_id' => $template->id,
                    'version' => $manifest['version'],
                    'disk' => $disk,
                    'path' => $path,
                    'checksum' => $checksum,
                    'fonts' => $manifest['fonts'],
                    'slot_labels' => $manifest['slot_labels'],
                    'placeholders' => $parsed->toArray(),
                    'published_at' => now(),
                ]);

                $latest = $template->latestVersion;

                if (! $latest || version_compare($version->version, $latest->version, '>')) {
                    $thumbnail = collect(self::THUMBNAILS)->first(fn (string $name) => isset($files[$name]));

                    $this->templates->update($template, [
                        ...$this->catalogueAttributes($manifest),
                        'latest_version_id' => $version->id,
                        'thumbnail_url' => $thumbnail ? Storage::disk($disk)->url("{$path}/{$thumbnail}") : null,
                    ]);
                }

                return $version;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->deleteDirectory($path);

            throw $e;
        }
    }

    /**
     * @return array{key: string, name: string, version: string, author: string, description: string|null, category: string, tags: list<string>, price: int, currency: string, display_price: string|null, fonts: list<string>, slot_labels: array<string, string>, sort_order: int, is_active: bool}
     */
    private function manifest(string $directory): array
    {
        $file = "{$directory}/template.json";

        if (! is_file($file)) {
            throw new InvalidTemplateException('The package has no template.json.');
        }

        $data = json_decode((string) file_get_contents($file), true);

        if (! is_array($data)) {
            throw new InvalidTemplateException('template.json is not valid JSON.');
        }

        $validator = Validator::make($data, [
            'key' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]{1,62}$/'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:32', 'regex:/^\d+\.\d+\.\d+$/'],
            'author' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::enum(TemplateCategory::class)],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:32'],
            'price' => ['integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'display_price' => ['nullable', 'string', 'max:64'],
            'fonts' => ['array'],
            'fonts.*' => ['string', 'url:https', 'max:2048'],
            'slot_labels' => ['array'],
            'slot_labels.*' => ['string', 'max:64'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            throw new InvalidTemplateException('template.json: '.implode(' ', $validator->errors()->all()));
        }

        return [
            'key' => $data['key'],
            'name' => $data['name'],
            'version' => $data['version'],
            'author' => $data['author'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'tags' => array_values($data['tags'] ?? []),
            'price' => (int) ($data['price'] ?? 0),
            'currency' => strtoupper($data['currency'] ?? (string) config('emp.default_currency')),
            'display_price' => $data['display_price'] ?? null,
            'fonts' => array_values($data['fonts'] ?? []),
            'slot_labels' => $data['slot_labels'] ?? [],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * @param  array{name: string, author: string, description: string|null, category: string, tags: list<string>, price: int, currency: string, display_price: string|null, sort_order: int, is_active: bool}  $manifest
     * @return array<string, mixed>
     */
    private function catalogueAttributes(array $manifest): array
    {
        return [
            'name' => $manifest['name'],
            'author' => $manifest['author'],
            'description' => $manifest['description'],
            'category' => $manifest['category'],
            'tags' => $manifest['tags'],
            'price' => $manifest['price'],
            'currency' => $manifest['currency'],
            'display_price' => $manifest['display_price'],
            'sort_order' => $manifest['sort_order'],
            'is_active' => $manifest['is_active'],
        ];
    }

    /** @return array<string, string> relative path => absolute path, sorted */
    private function packageFiles(string $directory): array
    {
        $files = [];

        /** @var SplFileInfo $file */
        foreach (File::allFiles($directory, hidden: false) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if (in_array($relative, ['template.html', 'styles.css', 'template.json', ...self::THUMBNAILS], true)) {
                $files[$relative] = $file->getPathname();

                continue;
            }

            $extension = strtolower($file->getExtension());

            if (! str_starts_with($relative, 'assets/') || ! in_array($extension, PlaceholderSyntax::ASSET_EXTENSIONS, true)) {
                throw new InvalidTemplateException("Unexpected file \"{$relative}\" in the package. Only template.json, template.html, styles.css, a thumbnail and images under assets/ are allowed.");
            }

            $files[$relative] = $file->getPathname();
        }

        ksort($files);

        return $files;
    }

    /** @param array<string, string> $files */
    private function checksum(array $files): string
    {
        $context = hash_init('sha256');

        foreach ($files as $relative => $absolute) {
            hash_update($context, $relative."\0".hash_file('sha256', $absolute)."\n");
        }

        return hash_final($context);
    }

    /** @return array{0: string, 1: string|null} the package directory, and a temporary directory to clean up */
    private function unpack(string $source): array
    {
        if (is_dir($source)) {
            return [rtrim($source, '/'), null];
        }

        if (! is_file($source) || strtolower(pathinfo($source, PATHINFO_EXTENSION)) !== 'zip') {
            throw new InvalidTemplateException("{$source} is not a folder or a .zip file.");
        }

        $zip = new ZipArchive;

        if ($zip->open($source) !== true) {
            throw new InvalidTemplateException("Could not open {$source}.");
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                $zip->close();

                throw new InvalidTemplateException("Unsafe path \"{$name}\" in the zip.");
            }
        }

        $temporary = storage_path('app/tmp/template-import-'.Str::uuid());
        File::ensureDirectoryExists($temporary);
        $zip->extractTo($temporary);
        $zip->close();

        // Allow zips that wrap everything in one top-level folder.
        $directory = $temporary;
        $entries = array_values(array_diff((array) scandir($temporary), ['.', '..', '__MACOSX']));

        if (count($entries) === 1 && is_dir("{$temporary}/{$entries[0]}")) {
            $directory = "{$temporary}/{$entries[0]}";
        }

        return [$directory, $temporary];
    }
}
