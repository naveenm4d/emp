<?php

namespace App\Domains\Template\Services;

use App\Core\Services\BaseService;
use App\Domains\Template\Contracts\TemplateImportServiceInterface;
use App\Domains\Template\Contracts\TemplateRepositoryInterface;
use App\Domains\Template\DTOs\EditableSchema;
use App\Domains\Template\DTOs\ParsedTemplate;
use App\Domains\Template\Enums\TemplateCategory;
use App\Domains\Template\Enums\TemplateType;
use App\Domains\Template\Exceptions\InvalidTemplateException;
use App\Domains\Template\Exceptions\TemplateVersionExistsException;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use App\Domains\Template\Support\MarkupSanitizer;
use App\Domains\Template\Support\PlaceholderParser;
use App\Domains\Template\Support\PlaceholderSyntax;
use Illuminate\Http\UploadedFile;
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
 *   index.html      markup with placeholders
 *   css/*.css       optional, concatenated in path order
 *   js/*.js         optional, run in path order once the invitation is shown
 *   thumbnail.webp  optional (png/jpg/webp)
 *   assets/…        optional images the code references with {{ asset:… }}
 *
 * Packages are uploaded by EMP staff, so their JS is trusted and not sanitised.
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

    public function importUploaded(UploadedFile $file): TemplateVersion
    {
        $directory = storage_path('app/tmp');
        File::ensureDirectoryExists($directory);
        $zip = $file->move($directory, Str::uuid().'.zip')->getPathname();

        try {
            return $this->import($zip);
        } finally {
            File::delete($zip);
        }
    }

    private function importDirectory(string $directory, bool $skipUnchanged): TemplateVersion
    {
        $manifest = $this->manifest($directory);
        $files = $this->packageFiles($directory);

        if (! isset($files['index.html'])) {
            throw new InvalidTemplateException('The package has no index.html.');
        }

        $markup = (string) file_get_contents($files['index.html']);
        $stylesheets = $this->filesIn($files, 'css/');
        $scripts = $this->filesIn($files, 'js/');

        $this->sanitizer->assertSafeMarkup($markup);

        foreach ($stylesheets as $stylesheet) {
            $this->sanitizer->assertSafeStyles((string) file_get_contents($files[$stylesheet]), $stylesheet);
        }

        $styles = implode("\n", array_map(fn (string $stylesheet) => (string) file_get_contents($files[$stylesheet]), $stylesheets));
        $parsed = $this->parser->parse($markup, $styles);

        foreach ($parsed->assets as $asset) {
            if (! isset($files["assets/{$asset}"])) {
                throw new InvalidTemplateException("{{ asset:{$asset} }} is used but assets/{$asset} is not in the package.");
            }
        }

        $slotKeys = array_map(fn ($slot) => $slot->key, $parsed->slots);

        foreach (array_keys($manifest['slot_labels']) as $key) {
            if (! in_array($key, $slotKeys, true)) {
                throw new InvalidTemplateException("slot_labels.{$key} does not match any media placeholder in index.html.");
            }
        }

        $this->assertEditableMatches($manifest['editable'], $parsed);

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
        $path = "templates/{$manifest['category']}/{$manifest['key']}/{$manifest['version']}";

        // Leftovers from an earlier failed import have no database row.
        Storage::disk($disk)->deleteDirectory($path);

        foreach ($files as $relative => $absolute) {
            Storage::disk($disk)->put("{$path}/{$relative}", (string) file_get_contents($absolute));
        }

        try {
            return $this->transaction(function () use ($template, $manifest, $disk, $path, $checksum, $files) {
                $template ??= $this->templates->create([
                    ...$this->catalogueAttributes($manifest),
                    'key' => $manifest['key'],
                    'type' => TemplateType::Predefined,
                ]);

                /** @var Template $template */
                $version = $this->templates->createVersion([
                    'template_id' => $template->id,
                    'version' => $manifest['version'],
                    'path' => $path,
                    'checksum' => $checksum,
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
     * @return array{key: string, name: string, version: string, author: string, description: string|null, category: string, tags: list<string>, price: int, currency: string, display_price: string|null, fonts: list<string>, slot_labels: array<string, string>, editable: EditableSchema, sort_order: int, is_active: bool}
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
            'editable' => ['array'],
            'editable.texts' => ['array'],
            'editable.texts.*.label' => ['required', 'string', 'max:64'],
            'editable.texts.*.default' => ['present', 'nullable', 'string', 'max:2000'],
            'editable.texts.*.multiline' => ['boolean'],
            'editable.texts.*.max' => ['integer', 'min:1', 'max:2000'],
            'editable.colors' => ['array'],
            'editable.colors.*.label' => ['required', 'string', 'max:64'],
            'editable.colors.*.default' => ['required', 'string', 'regex:'.EditableSchema::COLOR_PATTERN],
            'editable.sections' => ['array'],
            'editable.sections.*.label' => ['required', 'string', 'max:64'],
            'editable.sections.*.default' => ['boolean'],
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
            'editable' => $this->editable($data['editable'] ?? []),
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

            $extension = strtolower($file->getExtension());

            $allowed = in_array($relative, ['template.json', 'index.html', ...self::THUMBNAILS], true)
                || (str_starts_with($relative, 'css/') && $extension === 'css')
                || (str_starts_with($relative, 'js/') && $extension === 'js')
                || (str_starts_with($relative, 'assets/') && in_array($extension, PlaceholderSyntax::ASSET_EXTENSIONS, true));

            if (! $allowed) {
                throw new InvalidTemplateException("Unexpected file \"{$relative}\" in the package. Only template.json, index.html, css/*.css, js/*.js, a thumbnail and images under assets/ are allowed.");
            }

            $files[$relative] = $file->getPathname();
        }

        ksort($files);

        return $files;
    }

    /** @param array<string, mixed> $data the validated "editable" block of template.json */
    private function editable(array $data): EditableSchema
    {
        foreach (['texts', 'colors', 'sections'] as $kind) {
            foreach (array_keys($data[$kind] ?? []) as $key) {
                if (! preg_match(EditableSchema::KEY_PATTERN, (string) $key)) {
                    throw new InvalidTemplateException("template.json: editable.{$kind}.{$key} is not a valid key (lowercase letters, numbers and _, starting with a letter).");
                }
            }
        }

        $schema = EditableSchema::fromArray($data);

        foreach ($schema->texts as $key => $text) {
            if (mb_strlen($text['default']) > $text['max']) {
                throw new InvalidTemplateException("template.json: the default of editable.texts.{$key} is longer than its max ({$text['max']}).");
            }
        }

        return $schema;
    }

    /** Every text/section the markup uses must be declared, and every declared one used. */
    private function assertEditableMatches(EditableSchema $schema, ParsedTemplate $parsed): void
    {
        foreach (['text' => $schema->texts, 'section' => $schema->sections] as $kind => $declared) {
            $used = $parsed->editableKeys($kind);

            foreach (array_diff($used, array_keys($declared)) as $key) {
                throw new InvalidTemplateException("{{ {$kind}.{$key} }} is used in index.html but not declared in editable.{$kind}s of template.json.");
            }

            foreach (array_diff(array_keys($declared), $used) as $key) {
                throw new InvalidTemplateException("editable.{$kind}s.{$key} is declared in template.json but not used in index.html.");
            }
        }
    }

    /**
     * @param  array<string, string>  $files  relative path => absolute path, sorted
     * @return list<string> the relative paths under $prefix, in path order
     */
    private function filesIn(array $files, string $prefix): array
    {
        return array_values(array_filter(array_keys($files), fn (string $relative) => str_starts_with($relative, $prefix)));
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
