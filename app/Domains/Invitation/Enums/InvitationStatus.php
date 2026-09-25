<?php

namespace App\Domains\Invitation\Enums;

use App\Core\Enums\Concerns\HasValues;

enum InvitationStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';

    /** Only one active invitation may exist per guest. */
    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Sent;
    }

    public function isResponded(): bool
    {
        return $this === self::Accepted || $this === self::Declined;
    }

    public function canBeSent(): bool
    {
        return $this->isActive();
    }

    public function canBeResent(): bool
    {
        return $this === self::Sent;
    }
}
