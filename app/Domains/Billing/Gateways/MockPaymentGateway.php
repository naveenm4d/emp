<?php

namespace App\Domains\Billing\Gateways;

use App\Domains\Billing\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\DTOs\ChargeResult;
use App\Domains\Billing\DTOs\GatewayRedirect;
use App\Domains\Billing\DTOs\PayerData;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use Illuminate\Support\Str;

/**
 * Stands in for PayHere when it has no credentials (local runs, demos). The
 * payer lands on a local page that succeeds or fails the payment; saved
 * cards are charged without any network call. A card saved as "declines"
 * fails every charge, to try out failed renewals. Never trusted in production.
 */
class MockPaymentGateway implements PaymentGatewayInterface
{
    public const string MERCHANT_ID = 'mock';

    public const string DECLINING_TOKEN_PREFIX = 'mock-decline-';

    private const string SECRET = 'mock-secret';

    public function isMock(): bool
    {
        return true;
    }

    public function redirect(Checkout $checkout, PayerData $payer): GatewayRedirect
    {
        return new GatewayRedirect(route('client.billing.mock.show', $checkout), 'get', []);
    }

    public function verifyNotification(array $payload): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        return isset($payload['md5sig']) && is_string($payload['md5sig'])
            && hash_equals(self::signature($payload), $payload['md5sig']);
    }

    public function charge(Checkout $checkout, PaymentMethod $card): ChargeResult
    {
        if (str_starts_with((string) $card->customer_token, self::DECLINING_TOKEN_PREFIX)) {
            return new ChargeResult(PaymentStatus::Failed, null, 'Card declined (mock).');
        }

        return new ChargeResult(PaymentStatus::Succeeded, 'mock-'.Str::random(12), 'Successfully completed the payment (mock).');
    }

    public function confirm(Checkout $checkout): ?bool
    {
        return null;
    }

    public function refund(Payment $payment, string $reason): string
    {
        return 'mock-refund-'.Str::random(8);
    }

    /**
     * The notification the mock page sends, signed like PayHere signs it.
     *
     * @return array<string, string>
     */
    public static function notification(Checkout $checkout, PaymentStatus $status, bool $declineLater = false): array
    {
        $payload = [
            'merchant_id' => self::MERCHANT_ID,
            'order_id' => $checkout->order_id,
            'payment_id' => 'mock-'.Str::random(12),
            'payhere_amount' => $checkout->formattedAmount(),
            'payhere_currency' => $checkout->currency,
            'status_code' => match ($status) {
                PaymentStatus::Succeeded => '2',
                PaymentStatus::Canceled => '-1',
                default => '-2',
            },
            'status_message' => $status === PaymentStatus::Succeeded ? 'Successfully completed (mock)' : 'Declined (mock)',
            'method' => 'VISA',
            'card_holder_name' => 'Mock Card',
            'card_no' => '************4242',
            'card_expiry' => now()->addYears(3)->format('my'),
        ];

        if ($status === PaymentStatus::Succeeded) {
            $payload['customer_token'] = ($declineLater ? self::DECLINING_TOKEN_PREFIX : 'mock-token-').Str::random(24);
        }

        return [...$payload, 'md5sig' => self::signature($payload)];
    }

    /** @param array<string, mixed> $payload */
    private static function signature(array $payload): string
    {
        return strtoupper(md5(
            ($payload['merchant_id'] ?? '')
            .($payload['order_id'] ?? '')
            .($payload['payhere_amount'] ?? '')
            .($payload['payhere_currency'] ?? '')
            .($payload['status_code'] ?? '')
            .strtoupper(md5(self::SECRET)),
        ));
    }
}
