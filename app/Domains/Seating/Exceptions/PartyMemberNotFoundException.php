<?php

namespace App\Domains\Seating\Exceptions;

use App\Core\Exceptions\UnprocessableException;

class PartyMemberNotFoundException extends UnprocessableException
{
    protected string $errorCode = 'PARTY_MEMBER_NOT_FOUND';

    protected string $defaultMessage = "That person isn't in the party.";
}
