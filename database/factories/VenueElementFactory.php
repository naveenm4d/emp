<?php

namespace Database\Factories;

use App\Domains\Event\Models\Event;
use App\Domains\Seating\Enums\VenueElementKind;
use App\Domains\Seating\Models\VenueElement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VenueElement>
 */
class VenueElementFactory extends Factory
{
    protected $model = VenueElement::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'kind' => VenueElementKind::Stage,
            'label' => null,
            'pos_x' => 620,
            'pos_y' => 40,
            'width' => 360,
            'height' => 140,
        ];
    }
}
