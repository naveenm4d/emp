<?php

namespace App\Domains\Billing\Notifications;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Support\Money;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Emailed to the account owner when a payment goes through. */
class PaymentReceipt extends Notification
{
    public function __construct(
        public readonly Payment $payment,
    ) {}

    /** @return list<string> */
    public function via(ClientUser $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(ClientUser $notifiable): MailMessage
    {
        $payment = $this->payment;
        $card = $payment->paymentMethod?->label();

        return (new MailMessage)
            ->subject('Your '.config('app.name').' receipt: '.Money::format($payment->amount, $payment->currency))
            ->greeting("Hello {$notifiable->name},")
            ->line('Thank you. We received your payment.')
            ->line("**{$payment->description}**")
            ->line('Amount: '.Money::format($payment->amount, $payment->currency))
            ->line("Reference: {$payment->order_id}".($card ? " · {$card}" : ''))
            ->action('View receipt', route('client.billing.payments.show', $payment));
    }
}
