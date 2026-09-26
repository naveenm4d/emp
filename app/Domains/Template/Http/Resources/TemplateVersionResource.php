<?php

namespace App\Domains\Template\Http\Resources;

use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TemplateVersion */
class TemplateVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'published_at' => $this->published_at->toIso8601String(),
            'is_latest' => $this->template->latest_version_id === $this->id,
            'events_count' => $this->whenCounted('events'),
            'has_scripts' => $this->scripts() !== [],
            'slots' => count($this->slots()),
        ];
    }
}
