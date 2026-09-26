<?php

namespace Database\Factories;

use App\Domains\Event\Models\RegistrationQuestion;
use App\Domains\Guest\Models\Guest;
use App\Domains\Guest\Models\RegistrationAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationAnswer>
 */
class RegistrationAnswerFactory extends Factory
{
    protected $model = RegistrationAnswer::class;

    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'question_id' => RegistrationQuestion::factory(),
            'value' => 'Wheelchair access',
        ];
    }
}
