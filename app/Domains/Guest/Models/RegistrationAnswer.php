<?php

namespace App\Domains\Guest\Models;

use App\Domains\Event\Models\RegistrationQuestion;
use Carbon\CarbonImmutable;
use Database\Factories\RegistrationAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guest's answer to one of the event's custom questions.
 *
 * @property string $id
 * @property string $guest_id
 * @property string $question_id
 * @property string|int|float|bool|list<string> $value a list for multi-select questions
 * @property-read Guest $guest
 * @property-read RegistrationQuestion $question
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(RegistrationAnswerFactory::class)]
#[Fillable(['guest_id', 'question_id', 'value'])]
class RegistrationAnswer extends Model
{
    /** @use HasFactory<RegistrationAnswerFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /** @return BelongsTo<Guest, $this> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /** @return BelongsTo<RegistrationQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(RegistrationQuestion::class);
    }
}
