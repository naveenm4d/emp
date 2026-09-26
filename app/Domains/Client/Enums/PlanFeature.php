<?php

namespace App\Domains\Client\Enums;

use App\Core\Enums\Concerns\HasValues;

/** Parts of EMP that only some plans include. */
enum PlanFeature: string
{
    use HasValues;

    case PublicRegistration = 'public_registration';
    case Seating = 'seating';
    /** The event's RSVPs tab. */
    case RsvpList = 'rsvp_list';
    /** The event's Messages tab (message log and delivery details). */
    case MessageLog = 'message_log';

    public function label(): string
    {
        return match ($this) {
            self::PublicRegistration => 'Public registration',
            self::Seating => 'Seating',
            self::RsvpList => 'RSVP tracking',
            self::MessageLog => 'The message log',
        };
    }
}
