<?php

namespace App\Domains\Client\Notifications;

use App\Domains\Client\Models\ClientUser;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email asking someone to join a client account; the link lets them set a password. */
class ClientInvitation extends Notification
{
    public function __construct(
        public readonly string $token,
        public readonly string $accountName,
        public readonly string $invitedBy,
    ) {}

    /** @return list<string> */
    public function via(ClientUser $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(ClientUser $notifiable): MailMessage
    {
        $url = route('client.invitation.show', ['token' => $this->token, 'email' => $notifiable->email]);
        $days = (int) ceil(config('auth.passwords.client_invitations.expire') / 1440);

        return (new MailMessage)
            ->subject("Join {$this->accountName} on ".config('app.name'))
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->invitedBy} invited you to help manage events for {$this->accountName}.")
            ->action('Accept the invitation', $url)
            ->line("Choose a password to sign in. The link works for {$days} days.");
    }
}
