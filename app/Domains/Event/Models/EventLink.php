<?php

namespace App\Domains\Event\Models;

use App\Domains\Guest\Models\Guest;
use Carbon\CarbonImmutable;
use Database\Factories\EventLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short link, domain/{slug}/{code}. Without a guest it is the event's public
 * URL; with a guest it is that guest's personal RSVP link. The code identifies
 * the event (slugs are not unique); the slug is only a readable label.
 *
 * @property string $id
 * @property string $code 8 characters of CODE_ALPHABET, unique across all events
 * @property string $event_id
 * @property string|null $guest_id null for the event's public URL
 * @property int $open_count opens by people (link-preview crawlers are not counted)
 * @property CarbonImmutable|null $last_opened_at
 * @property-read Event $event
 * @property-read Guest|null $guest
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(EventLinkFactory::class)]
#[Fillable(['code', 'event_id', 'guest_id', 'open_count', 'last_opened_at'])]
class EventLink extends Model
{
    /** @use HasFactory<EventLinkFactory> */
    use HasFactory, HasUuids;

    /** Lowercase letters and digits without look-alikes (0/o, 1/l/i). */
    public const string CODE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public const int CODE_LENGTH = 8;

    protected $attributes = [
        'open_count' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'open_count' => 'integer',
            'last_opened_at' => 'datetime',
        ];
    }

    public static function generateCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    public function isForGuest(): bool
    {
        return $this->guest_id !== null;
    }

    /** Absolute URL with the event's current slug. */
    public function url(): string
    {
        $this->loadMissing('event');

        return route('web.link', ['slug' => $this->event->slug, 'code' => $this->code]);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Guest, $this> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }
}
