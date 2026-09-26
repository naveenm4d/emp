<?php

namespace App\Domains\Template\Http\Resources;

use App\Domains\Template\DTOs\MediaSlot;
use App\Domains\Template\Enums\MediaType;
use App\Domains\Template\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Catalogue entry shown in the client's template picker.
 *
 * @mixin Template
 */
class TemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $slots = $this->latestVersion?->slots() ?? [];
        $count = fn (MediaType $type) => count(array_filter($slots, fn (MediaSlot $slot) => $slot->type === $type));

        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'author' => $this->author,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'tags' => $this->tags,
            'price' => $this->price,
            'currency' => $this->currency,
            'display_price' => $this->display_price ?? ($this->isFree() ? 'Free' : null),
            'is_free' => $this->isFree(),
            'type' => $this->type->value,
            'thumbnail_url' => $this->thumbnail_url,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'versions_count' => $this->whenCounted('versions'),
            'client' => $this->whenLoaded('client', fn () => $this->client ? ['id' => $this->client->id, 'name' => $this->client->name] : null),
            'version' => $this->latestVersion?->version,
            'slots' => array_map(fn (MediaSlot $slot) => $slot->toArray(), $slots),
            'media_summary' => [
                'images' => $count(MediaType::Image),
                'videos' => $count(MediaType::Video),
                'music' => $count(MediaType::Audio) > 0,
            ],
        ];
    }
}
