<?php

namespace App\Domains\Notification\Channels;

use App\Domains\Notification\Contracts\ChannelProviderInterface;
use App\Domains\Notification\DTOs\ProviderResult;
use App\Domains\Notification\Enums\NotificationChannel;
use App\Domains\Notification\Exceptions\ChannelDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Meta WhatsApp Cloud API. Falls back to a mock message id when no
 * credentials are configured, so local development never sends for real.
 */
class WhatsAppChannel implements ChannelProviderInterface
{
    public function channel(): NotificationChannel
    {
        return NotificationChannel::WhatsApp;
    }

    public function send(string $recipient, string $message): ProviderResult
    {
        $config = config('services.whatsapp');

        if (blank($config['api_token']) || blank($config['phone_number_id'])) {
            Log::info('WhatsApp credentials missing; using mock provider.', ['to' => $recipient]);

            return new ProviderResult('mock-wamid-'.Str::uuid(), ['mock' => true]);
        }

        try {
            $response = Http::withToken($config['api_token'])
                ->acceptJson()
                ->timeout(10)
                ->post("{$config['graph_url']}/{$config['phone_number_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($recipient, '+'),
                    'type' => 'text',
                    'text' => ['preview_url' => true, 'body' => $message],
                ]);
        } catch (ConnectionException $e) {
            throw new ChannelDeliveryException('WhatsApp API unreachable: '.$e->getMessage(), previous: $e);
        }

        $messageId = $response->json('messages.0.id');

        if ($response->failed() || ! is_string($messageId)) {
            throw new ChannelDeliveryException(sprintf(
                'WhatsApp API error (%d): %s',
                $response->status(),
                $response->json('error.message') ?? $response->body(),
            ));
        }

        return new ProviderResult($messageId, (array) $response->json());
    }
}
