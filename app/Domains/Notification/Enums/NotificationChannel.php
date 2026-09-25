<?php

namespace App\Domains\Notification\Enums;

use App\Core\Enums\Concerns\HasValues;

enum NotificationChannel: string
{
    use HasValues;

    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Sms = 'sms';
}
