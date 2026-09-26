<?php

namespace App\Domains\Rsvp\Enums;

use App\Core\Enums\Concerns\HasValues;

enum RsvpStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Maybe = 'maybe';
    case Expired = 'expired';

    /** Only one active RSVP link may exist per guest. */
    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Sent;
    }

    public function isResponded(): bool
    {
        return $this === self::Accepted || $this === self::Declined || $this === self::Maybe;
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
