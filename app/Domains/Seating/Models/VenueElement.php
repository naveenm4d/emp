<?php

namespace App\Domains\Seating\Models;

use App\Domains\Event\Models\Event;
use App\Domains\Seating\Enums\VenueElementKind;
use Carbon\CarbonImmutable;
use Database\Factories\VenueElementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something on the seating floor plan that isn't a guest table: the stage,
 * the poruwa, the buffet, the entrance, … Placed in canvas units.
 *
 * @property string $id
 * @property string $event_id
 * @property VenueElementKind $kind
 * @property string|null $label required for custom elements; otherwise the kind's label is shown
 * @property int $pos_x
 * @property int $pos_y
 * @property int $width
 * @property int $height
 * @property-read Event $event
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(VenueElementFactory::class)]
#[Fillable(['event_id', 'kind', 'label', 'pos_x', 'pos_y', 'width', 'height'])]
class VenueElement extends Model
{
    /** @use HasFactory<VenueElementFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => VenueElementKind::class,
            'pos_x' => 'integer',
            'pos_y' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /** What the floor plan shows on it. */
    public function displayLabel(): string
    {
        return $this->label ?? $this->kind->label();
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
