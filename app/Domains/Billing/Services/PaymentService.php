<?php

namespace App\Domains\Billing\Services;

use App\Core\Services\BaseService;
use App\Domains\Billing\Contracts\CheckoutRepositoryInterface;
use App\Domains\Billing\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\Contracts\PaymentMethodServiceInterface;
use App\Domains\Billing\Contracts\PaymentRepositoryInterface;
use App\Domains\Billing\Contracts\PaymentServiceInterface;
use App\Domains\Billing\DTOs\CardDetails;
use App\Domains\Billing\Enums\PaymentChannel;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Events\PaymentFailed;
use App\Domains\Billing\Events\PaymentSucceeded;
use App\Domains\Billing\Exceptions\PaymentMethodUnavailableException;
use App\Domains\Billing\Exceptions\RefundNotAllowedException;
use App\Domains\Billing\Jobs\ChargePreapprovedCheckout;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Support\Money;
use App\Domains\Staff\Contracts\StaffActivityServiceInterface;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentService extends BaseService implements PaymentServiceInterface
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly CheckoutRepositoryInterface $checkouts,
        private readonly PaymentGatewayInterface $gateway,
        private readonly PaymentMethodServiceInterface $cards,
        private readonly FulfilmentService $fulfilment,
        private readonly StaffActivityServiceInterface $activities,
    ) {}

    public function handleNotification(array $payload): void
    {
        $orderId = is_string($payload['order_id'] ?? null) ? $payload['order_id'] : null;
        // The card token is a credential: it never goes into logs.
        $logged = Arr::except($payload, ['customer_token']);

        if (! $this->gateway->verifyNotification($payload)) {
            $this->payments->logNotification($orderId, $logged, false, 'Invalid signature or merchant.');
            Log::warning('Rejected a PayHere notification with an invalid signature.', ['order_id' => $orderId]);

            return;
        }

        // Already processed (a decline retried, a chargeback), or still a checkout.
        $payment = $orderId !== null ? $this->payments->findByOrderId($orderId) : null;
        $checkout = $payment === null && $orderId !== null ? $this->checkouts->findByOrderId($orderId) : null;
        $order = $payment ?? $checkout;

        if ($order === null) {
            $this->payments->logNotification($orderId, $logged, true, 'Unknown or expired order.');

            return;
        }

        $status = PaymentStatus::fromPayHere((int) $payload['status_code']);
        $payhereId = is_scalar($payload['payment_id'] ?? null) ? mb_substr((string) $payload['payment_id'], 0, 64) : null;
        $message = is_scalar($payload['status_message'] ?? null) ? (string) $payload['status_message'] : null;

        if (! $this->amountMatches($order->formattedAmount(), $order->currency, $payload)) {
            $this->payments->logNotification($orderId, $logged, true, 'Amount or currency differs from the order.');
            Log::critical('PayHere reported a different amount than the order.', [
                'order_id' => $orderId, 'expected' => $order->formattedAmount().' '.$order->currency,
                'received' => ($payload['payhere_amount'] ?? '').' '.($payload['payhere_currency'] ?? ''),
            ]);

            if ($checkout !== null && $status->isFinal()) {
                $this->record($checkout, PaymentStatus::Failed, $payhereId, 'The amount paid didn\'t match the order.');
            }

            return;
        }

        $this->payments->logNotification($orderId, $logged, true);

        if ($payment !== null) {
            if ($status !== PaymentStatus::Pending && $status !== PaymentStatus::Canceled) {
                $this->settle($payment, $status, $payhereId, $message);
            }

            return;
        }

        /** @var Checkout $checkout */
        if ($checkout->channel === PaymentChannel::Preapproval) {
            $this->handlePreapproval($checkout, $status, $payload, $payhereId, $message);

            return;
        }

        // Belt and braces: PayHere's own records must show the money (when an API key is set).
        if ($status === PaymentStatus::Succeeded && $this->gateway->confirm($checkout) === false) {
            Log::critical('A signed PayHere notification was not confirmed by the Retrieval API.', ['order_id' => $orderId]);

            return;
        }

        $this->record($checkout, $status, $payhereId, $message);
    }

    public function record(Checkout $checkout, PaymentStatus $status, ?string $payherePaymentId = null, ?string $message = null): ?Payment
    {
        $payment = $this->transaction(function () use ($checkout, $status, $payherePaymentId, $message) {
            $locked = $this->checkouts->findAndLock($checkout->id);

            // Processed meanwhile (a repeated notification): the payment is already there.
            if ($locked === null) {
                return $this->payments->findByOrderId($checkout->order_id);
            }

            if ($status === PaymentStatus::Pending) {
                if ($payherePaymentId !== null) {
                    $this->checkouts->update($locked, ['payhere_payment_id' => $payherePaymentId]);
                }

                return null;
            }

            // The payer left PayHere's page: nothing was processed, nothing is recorded.
            if ($status === PaymentStatus::Canceled) {
                $this->checkouts->delete($locked);

                return null;
            }

            /** @var Payment $payment */
            $payment = $this->payments->create([
                ...$locked->only([
                    'client_id', 'order_id', 'purpose', 'channel', 'description', 'subject_type', 'subject_id',
                    'subscription_id', 'renewal_period_end', 'details', 'amount', 'currency', 'payment_method_id', 'initiated_by',
                ]),
                'status' => $status === PaymentStatus::Succeeded ? PaymentStatus::Succeeded : PaymentStatus::Failed,
                'payhere_payment_id' => $payherePaymentId ?? $locked->payhere_payment_id,
                'status_message' => $message !== null ? mb_substr($message, 0, 500) : null,
                'paid_at' => $status === PaymentStatus::Succeeded ? now() : null,
            ]);

            $this->checkouts->delete($locked);

            if ($payment->status === PaymentStatus::Failed) {
                $this->failed($payment);
            }

            return $payment;
        });

        return $payment?->status === PaymentStatus::Succeeded ? $this->fulfil($payment) : $payment;
    }

    public function settle(Payment $payment, PaymentStatus $status, ?string $payherePaymentId = null, ?string $message = null): Payment
    {
        $payment = $this->transaction(function () use ($payment, $status, $payherePaymentId, $message) {
            $locked = $this->payments->findAndLock($payment->id);

            if (! $locked->status->canBecome($status)) {
                return $locked;
            }

            $attributes = ['status' => $status];
            $attributes += match ($status) {
                PaymentStatus::Succeeded => ['paid_at' => now()],
                PaymentStatus::Refunded => ['refunded_at' => now()],
                default => [],
            };

            if ($payherePaymentId !== null) {
                $attributes['payhere_payment_id'] = $payherePaymentId;
            }

            if ($message !== null) {
                $attributes['status_message'] = mb_substr($message, 0, 500);
            }

            /** @var Payment $locked */
            $locked = $this->payments->update($locked, $attributes);

            if ($status === PaymentStatus::Chargedback) {
                Log::warning('A PayHere payment was charged back.', ['order_id' => $locked->order_id]);
            }

            return $locked;
        });

        // Applied in its own transaction: if it fails, the payment is still recorded as paid
        // (and retried by billing:renew-subscriptions) instead of rolling back the money.
        return $payment->status === PaymentStatus::Succeeded ? $this->fulfil($payment) : $payment;
    }

    public function chargeSavedCard(Checkout $checkout, PaymentMethod $card): Payment|Checkout
    {
        if ($card->client_id !== $checkout->client_id || ! $card->isChargeable()) {
            throw new PaymentMethodUnavailableException;
        }

        if ($checkout->payment_method_id !== $card->id) {
            /** @var Checkout $checkout */
            $checkout = $this->checkouts->update($checkout, ['payment_method_id' => $card->id]);
        }

        $result = $this->gateway->charge($checkout, $card);

        // A charge has no "left the page": anything but paid or pending is a decline.
        $status = match ($result->status) {
            PaymentStatus::Succeeded, PaymentStatus::Pending => $result->status,
            default => PaymentStatus::Failed,
        };

        return $this->record($checkout, $status, $result->paymentId, $result->message) ?? $checkout;
    }

    public function fulfil(Payment $payment): Payment
    {
        try {
            return $this->transaction(function () use ($payment) {
                $locked = $this->payments->findAndLock($payment->id);

                if ($locked->status !== PaymentStatus::Succeeded || $locked->fulfilled_at !== null) {
                    return $locked;
                }

                $this->fulfilment->fulfil($locked);

                /** @var Payment $locked */
                $locked = $this->payments->update($locked, ['fulfilled_at' => now()]);

                PaymentSucceeded::dispatch($locked);

                return $locked;
            });
        } catch (Throwable $e) {
            report($e);
            Log::error('A paid payment could not be applied; it will be retried.', ['order_id' => $payment->order_id]);

            return $payment->refresh();
        }
    }

    public function refund(Payment $payment, StaffMember $by, string $note): Payment
    {
        if ($payment->status !== PaymentStatus::Succeeded || $payment->payhere_payment_id === null || $payment->purpose === PaymentPurpose::CardSetup) {
            throw new RefundNotAllowedException;
        }

        $reference = $this->gateway->refund($payment, $note);

        return $this->transaction(function () use ($payment, $by, $note, $reference) {
            $payment = $this->settle($payment, PaymentStatus::Refunded, null, 'Refunded'.($reference !== '' ? " (PayHere refund {$reference})" : '').'.');

            $this->activities->record(
                staff: $by,
                action: 'admin.billing.payments.refund',
                description: "Refunded {$payment->order_id}: ".Money::format($payment->amount, $payment->currency)." for {$payment->description}",
                subject: $payment,
                clientId: $payment->client_id,
                changes: ['refund_reference' => $reference],
                note: $note,
            );

            return $payment;
        });
    }

    /**
     * Preapproval: PayHere saved the card. Card setups are done; purchases are
     * then charged to the new card (once: a repeated notification finds the card already stored).
     *
     * @param  array<string, mixed>  $payload
     */
    private function handlePreapproval(Checkout $checkout, PaymentStatus $status, array $payload, ?string $payhereId, ?string $message): void
    {
        if ($status !== PaymentStatus::Succeeded) {
            $this->record($checkout, $status, $payhereId, $message);

            return;
        }

        $card = CardDetails::fromNotification($payload);

        if ($card === null) {
            $this->record($checkout, PaymentStatus::Failed, $payhereId, 'PayHere didn\'t send a card token.');

            return;
        }

        $toCharge = $this->transaction(function () use ($checkout, $card) {
            $locked = $this->checkouts->findAndLock($checkout->id);

            if ($locked === null || $locked->payment_method_id !== null) {
                return null;
            }

            $method = $this->cards->store($locked->client, $card, $locked->initiated_by);

            /** @var Checkout */
            return $this->checkouts->update($locked, ['payment_method_id' => $method->id]);
        });

        if ($toCharge === null) {
            return;
        }

        if ($toCharge->purpose === PaymentPurpose::CardSetup) {
            $this->record($toCharge, PaymentStatus::Succeeded, $payhereId, 'Card saved.');

            return;
        }

        ChargePreapprovedCheckout::dispatch($toCharge->id);
    }

    /** @param array<string, mixed> $payload */
    private function amountMatches(string $amount, string $currency, array $payload): bool
    {
        $reported = $payload['payhere_amount'] ?? null;

        return is_numeric($reported)
            && number_format((float) $reported, 2, '.', '') === $amount
            && ($payload['payhere_currency'] ?? null) === $currency;
    }

    /** PayHere declined it: a declined renewal ends the subscription. */
    private function failed(Payment $payment): void
    {
        $this->fulfilment->failed($payment);

        PaymentFailed::dispatch($payment);
    }
}
