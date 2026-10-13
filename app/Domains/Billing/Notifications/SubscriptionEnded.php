<?php

namespace App\Domains\Billing\Notifications;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A canceled subscription reached the end of its period. */
class SubscriptionEnded extends Notification
{
    public function __construct(
        public readonly Subscription $subscription,
    ) {}

    /** @return list<string> */
    public function via(ClientUser $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(ClientUser $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.config('app.name').' Business plan has ended')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your Business plan ended on {$this->subscription->current_period_end->format('j M Y')}, as you asked.")
            ->line('Your events and guests are still here. Subscribe again any time to pick up where you left off.')
            ->action('Go to Membership', route('client.membership'));
    }
}
