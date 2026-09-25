<?php

namespace Database\Factories;

use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use App\Domains\Template\Enums\MediaType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventMedia>
 */
class EventMediaFactory extends Factory
{
    protected $model = EventMedia::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'slot_key' => 'img_1',
            'type' => MediaType::Image,
            'disk' => fn () => config('emp.media_disk'),
            'path' => fn (array $attributes) => "events/{$attributes['event_id']}/media/".Str::uuid().'.jpg',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ];
    }

    public function slot(string $key, MediaType $type = MediaType::Image): static
    {
        return $this->state(['slot_key' => $key, 'type' => $type]);
    }
}
