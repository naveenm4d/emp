<?php

namespace App\Domains\Notification\Enums;

use App\Core\Enums\Concerns\HasValues;

enum NotificationStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
