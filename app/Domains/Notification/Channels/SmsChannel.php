<?php

namespace App\Domains\Notification\Channels;

use App\Domains\Notification\Contracts\ChannelProviderInterface;
use App\Domains\Notification\DTOs\ProviderResult;
use App\Domains\Notification\Enums\NotificationChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Placeholder until a Sms provider is chosen. Logs and returns a mock id.
 */
class SmsChannel implements ChannelProviderInterface
{
    public function channel(): NotificationChannel
    {
        return NotificationChannel::Sms;
    }

    public function send(string $recipient, string $message): ProviderResult
    {
        Log::info('Sms channel is not configured; message logged only.', ['to' => $recipient]);

        return new ProviderResult('mock-sms-'.Str::uuid(), ['mock' => true]);
    }
}
