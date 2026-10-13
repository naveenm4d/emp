<?php

namespace App\Domains\Billing\Notifications;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The Business renewal couldn't be charged: the plan ends with the current period. */
class RenewalFailed extends Notification
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly ?string $reason = null,
    ) {}

    /** @return list<string> */
    public function via(ClientUser $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(ClientUser $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We couldn\'t renew your '.config('app.name').' Business plan')
            ->greeting("Hello {$notifiable->name},")
            ->line('We couldn\'t renew your Business plan'.($this->reason ? " ({$this->reason})" : '').'.')
            ->line("Your plan ends on {$this->subscription->current_period_end->format('j M Y')}.")
            ->line('Subscribe again with another card to keep your Business features.')
            ->action('Go to Membership', route('client.membership'));
    }
}
