<?php

namespace App\Domains\Seating\Http\Resources;

use App\Domains\Seating\Models\VenueElement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A venue element on the seating floor plan.
 *
 * @mixin VenueElement
 */
class VenueElementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'label' => $this->label,
            'display_label' => $this->displayLabel(),
            'x' => $this->pos_x,
            'y' => $this->pos_y,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
