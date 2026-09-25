<?php

namespace App\Domains\Template\Models;

use App\Domains\Client\Models\Client;
use App\Domains\Template\Enums\TemplateCategory;
use App\Domains\Template\Enums\TemplateType;
use Carbon\CarbonImmutable;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A catalogue entry clients pick from. Its code lives in TemplateVersion.
 *
 * @property string $id
 * @property string $key stable identifier, e.g. "floral"
 * @property string $name
 * @property string|null $description
 * @property string $author
 * @property TemplateCategory $category
 * @property list<string> $tags
 * @property int $price minor units
 * @property string $currency
 * @property string|null $display_price
 * @property TemplateType $type
 * @property string|null $client_id owner of a custom template
 * @property string|null $thumbnail_url
 * @property string|null $latest_version_id
 * @property bool $is_active
 * @property int $sort_order
 * @property-read TemplateVersion|null $latestVersion
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(TemplateFactory::class)]
#[Fillable([
    'key', 'name', 'description', 'author', 'category', 'tags', 'price', 'currency',
    'display_price', 'type', 'client_id', 'thumbnail_url', 'latest_version_id',
    'is_active', 'sort_order',
])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'type' => 'predefined',
        'tags' => '[]',
        'price' => 0,
        'is_active' => true,
        'sort_order' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => TemplateCategory::class,
            'type' => TemplateType::class,
            'tags' => 'array',
            'price' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    /** @return HasMany<TemplateVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    /** @return BelongsTo<TemplateVersion, $this> */
    public function latestVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'latest_version_id');
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
