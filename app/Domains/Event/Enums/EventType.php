<?php

namespace App\Domains\Event\Enums;

use App\Core\Enums\Concerns\HasValues;

/** The kind of occasion an event is, chosen from a fixed list in the event form. */
enum EventType: string
{
    use HasValues;

    case Wedding = 'wedding';
    case Engagement = 'engagement';
    case Birthday = 'birthday';
    case BabyShower = 'baby_shower';
    case Anniversary = 'anniversary';
    case Corporate = 'corporate';
    case Conference = 'conference';
    case Party = 'party';
    case Community = 'community';
    case Other = 'other';
}
