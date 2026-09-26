<?php

namespace App\Domains\Event\Exceptions;

use App\Core\Exceptions\ConflictException;

class QuestionHasAnswersException extends ConflictException
{
    protected string $errorCode = 'QUESTION_HAS_ANSWERS';

    protected string $defaultMessage = "Guests have already answered this question, so its type can't be changed. Add a new question instead.";
}
