<?php

namespace App\Domains\Notification\Contracts;

use App\Domains\Notification\DTOs\ProviderResult;
use App\Domains\Notification\Enums\NotificationChannel;
use App\Domains\Notification\Exceptions\ChannelDeliveryException;

interface ChannelProviderInterface
{
    public function channel(): NotificationChannel;

    /** @throws ChannelDeliveryException */
    public function send(string $recipient, string $message): ProviderResult;
}
