<?php

namespace App\Domains\Event\Models;

use App\Domains\Event\Enums\QuestionType;
use App\Domains\Guest\Models\RegistrationAnswer;
use Carbon\CarbonImmutable;
use Database\Factories\RegistrationQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A custom question the client added to the event's registration / RSVP form.
 *
 * @property string $id
 * @property string $event_id
 * @property string $label
 * @property QuestionType $type
 * @property list<string>|null $options the choices, for select, multi-select and radio questions
 * @property bool $required
 * @property int $sort_order
 * @property-read Event $event
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(RegistrationQuestionFactory::class)]
#[Fillable(['event_id', 'label', 'type', 'options', 'required', 'sort_order'])]
class RegistrationQuestion extends Model
{
    /** @use HasFactory<RegistrationQuestionFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'options' => 'array',
            'required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<RegistrationAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(RegistrationAnswer::class, 'question_id');
    }
}
