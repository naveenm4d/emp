<?php

namespace App\Domains\Guest\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Guest\Models\RegistrationAnswer;

/**
 * @extends RepositoryInterface<RegistrationAnswer>
 */
interface RegistrationAnswerRepositoryInterface extends RepositoryInterface
{
    /**
     * Stores the guest's answers; a null answer removes it.
     *
     * @param  array<string, mixed>  $answers  question id => answer
     */
    public function sync(string $guestId, array $answers): void;
}
