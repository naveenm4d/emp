<?php

namespace App\Domains\Billing\Notifications;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A few days before a yearly renewal is charged. */
class RenewalUpcoming extends Notification
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $amount,
    ) {}

    /** @return list<string> */
    public function via(ClientUser $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(ClientUser $notifiable): MailMessage
    {
        $card = $this->subscription->paymentMethod?->label() ?? 'your saved card';

        return (new MailMessage)
            ->subject('Your '.config('app.name').' Business plan renews soon')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your Business plan renews on {$this->subscription->current_period_end->format('j M Y')}. We'll charge {$this->amount} to {$card}.")
            ->line('Nothing to do if you want to keep it. To stop it, turn off auto-renew on the Membership page before then.')
            ->action('Manage subscription', route('client.membership'));
    }
}
