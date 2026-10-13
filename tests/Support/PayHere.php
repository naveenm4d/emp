<?php

namespace Tests\Support;

use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use Illuminate\Support\Facades\Http;

/** PayHere in tests: sandbox credentials, signed notifications and faked merchant API calls. */
final class PayHere
{
    public const string MERCHANT_ID = '1211149';

    public const string SECRET = 'test-merchant-secret';

    public const string API = 'https://sandbox.payhere.lk/merchant/v1';

    /** Real gateway with sandbox credentials; every API call must be faked. */
    public static function configure(): void
    {
        config(['services.payhere' => [
            'merchant_id' => self::MERCHANT_ID,
            'merchant_secret' => self::SECRET,
            'app_id' => 'test-app',
            'app_secret' => 'test-app-secret',
            'sandbox' => true,
            'notify_url' => null,
        ]]);

        // Pages render without SSR: no request may leave the test, not even to Vite.
        config(['inertia.ssr.enabled' => false]);
        Http::preventStrayRequests();
    }

    /**
     * What PayHere posts to the notify URL, signed with the merchant secret.
     *
     * @param  array<string, string>  $overrides  applied before signing
     * @return array<string, string>
     */
    public static function notification(Payment|Checkout $payment, int $statusCode = 2, array $overrides = []): array
    {
        $payload = [
            'merchant_id' => self::MERCHANT_ID,
            'order_id' => $payment->order_id,
            'payment_id' => '320025071278',
            'payhere_amount' => $payment->formattedAmount(),
            'payhere_currency' => $payment->currency,
            'status_code' => (string) $statusCode,
            'status_message' => $statusCode === 2 ? 'Successfully completed the payment.' : 'Declined',
            'method' => 'VISA',
            'card_holder_name' => 'Ada Perera',
            'card_no' => '************1292',
            'card_expiry' => '1229',
            ...$overrides,
        ];

        return [...$payload, 'md5sig' => self::signature($payload)];
    }

    /** @param array<string, string> $payload */
    public static function signature(array $payload, string $secret = self::SECRET): string
    {
        return strtoupper(md5(
            $payload['merchant_id'].$payload['order_id'].$payload['payhere_amount'].$payload['payhere_currency']
            .$payload['status_code'].strtoupper(md5($secret)),
        ));
    }

    /**
     * Fakes OAuth plus the given API answers.
     *
     * @param  array<string, mixed>  $responses  path under /merchant/v1 => response
     */
    public static function fakeApi(array $responses): void
    {
        $fakes = [self::API.'/oauth/token' => Http::response(['access_token' => 'test-token', 'token_type' => 'bearer', 'expires_in' => 599])];

        foreach ($responses as $path => $response) {
            $fakes[self::API.$path] = $response;
        }

        Http::fake($fakes);
    }

    /** The Retrieval API's answer: the payment was received (or no record). */
    public static function retrieval(Payment|Checkout|null $received): mixed
    {
        return $received === null
            ? Http::response(['status' => -1, 'msg' => 'No payments found'])
            : Http::response(['status' => 1, 'msg' => 'Payments with order_id:'.$received->order_id, 'data' => [[
                'payment_id' => 320025071278,
                'order_id' => $received->order_id,
                'currency' => $received->currency,
                'amount' => $received->amount / 100,
                'status' => 'RECEIVED',
            ]]]);
    }

    /** The Charging API's answer (status_code 2 = paid, -2 = declined). */
    public static function charge(int $statusCode = 2, string $message = 'Successfully completed the payment.'): mixed
    {
        return Http::response(self::chargeBody($statusCode, $message));
    }

    /** @return array<string, mixed> */
    public static function chargeBody(int $statusCode = 2, string $message = 'Successfully completed the payment.'): array
    {
        return ['status' => 1, 'msg' => 'Automatic payment charged successfully', 'data' => [
            'payment_id' => 320025023469,
            'status_code' => $statusCode,
            'status_message' => $message,
        ]];
    }
}
