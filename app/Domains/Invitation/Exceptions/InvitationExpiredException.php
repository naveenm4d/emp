<?php

namespace App\Domains\Invitation\Exceptions;

use App\Core\Exceptions\GoneException;

class InvitationExpiredException extends GoneException
{
    protected string $errorCode = 'INVITATION_EXPIRED';

    protected string $defaultMessage = 'This invitation has expired.';
}
