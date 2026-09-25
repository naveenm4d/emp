<?php

namespace App\Domains\Event\Models;

use App\Domains\Template\Enums\MediaType;
use Carbon\CarbonImmutable;
use Database\Factories\EventMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file a client uploaded into one of their template's media slots.
 *
 * @property string $id
 * @property string $event_id
 * @property string $slot_key e.g. img_1, video_1, bg_music
 * @property MediaType $type
 * @property string $disk
 * @property string $path
 * @property string|null $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property-read Event $event
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(EventMediaFactory::class)]
#[Fillable(['event_id', 'slot_key', 'type', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes'])]
class EventMedia extends Model
{
    /** @use HasFactory<EventMediaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'event_media';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'size_bytes' => 'integer',
        ];
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
