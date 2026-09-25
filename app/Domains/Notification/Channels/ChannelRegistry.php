<?php

namespace App\Domains\Notification\Channels;

use App\Domains\Notification\Contracts\ChannelProviderInterface;
use App\Domains\Notification\Enums\NotificationChannel;
use InvalidArgumentException;

/**
 * Resolves the provider responsible for a channel.
 */
class ChannelRegistry
{
    /** @var array<string, ChannelProviderInterface> */
    private array $providers = [];

    /** @param iterable<ChannelProviderInterface> $providers */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->channel()->value] = $provider;
        }
    }

    public function for(NotificationChannel $channel): ChannelProviderInterface
    {
        return $this->providers[$channel->value]
            ?? throw new InvalidArgumentException("No provider registered for channel [{$channel->value}].");
    }
}
