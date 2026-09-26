<?php

namespace Database\Factories;

use App\Domains\Event\Enums\QuestionType;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\RegistrationQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationQuestion>
 */
class RegistrationQuestionFactory extends Factory
{
    protected $model = RegistrationQuestion::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'label' => 'Any special requirements?',
            'type' => QuestionType::Text,
            'options' => null,
            'required' => false,
            'sort_order' => 0,
        ];
    }

    /** @param list<string> $options */
    public function choice(QuestionType $type = QuestionType::Select, array $options = ['S', 'M', 'L']): static
    {
        return $this->state(['type' => $type, 'options' => $options]);
    }

    public function required(): static
    {
        return $this->state(['required' => true]);
    }
}
