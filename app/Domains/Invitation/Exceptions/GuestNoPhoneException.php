<?php

namespace App\Domains\Invitation\Exceptions;

use App\Core\Exceptions\UnprocessableException;

class GuestNoPhoneException extends UnprocessableException
{
    protected string $errorCode = 'GUEST_NO_PHONE';

    protected string $defaultMessage = 'The guest has no phone number to send the invitation to.';
}
