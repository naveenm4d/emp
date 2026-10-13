<?php

namespace App\Domains\Billing\Gateways;

use App\Domains\Billing\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\DTOs\ChargeResult;
use App\Domains\Billing\DTOs\GatewayRedirect;
use App\Domains\Billing\DTOs\PayerData;
use App\Domains\Billing\Enums\PaymentChannel;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Exceptions\PaymentGatewayException;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayHere (https://support.payhere.lk/api-&-mobile-sdk). The checkout hash
 * and notification md5sig are built from the merchant secret, so both only
 * happen here, on the server.
 */
class PayHereGateway implements PaymentGatewayInterface
{
    private const string TOKEN_CACHE_KEY = 'billing.payhere.access_token';

    public function __construct(
        private readonly string $merchantId,
        private readonly string $merchantSecret,
        private readonly ?string $appId,
        private readonly ?string $appSecret,
        private readonly bool $sandbox,
        private readonly ?string $notifyUrl,
    ) {}

    public function isMock(): bool
    {
        return false;
    }

    public function redirect(Checkout $checkout, PayerData $payer): GatewayRedirect
    {
        $path = $checkout->channel === PaymentChannel::Preapproval ? '/pay/preapprove' : '/pay/checkout';

        return new GatewayRedirect(
            url: $this->baseUrl().$path,
            method: 'post',
            fields: [
                'merchant_id' => $this->merchantId,
                'return_url' => route('client.billing.orders.show', $checkout->order_id),
                'cancel_url' => route('client.billing.orders.show', ['order' => $checkout->order_id, 'canceled' => 1]),
                'notify_url' => $this->notifyUrl(),
                'order_id' => $checkout->order_id,
                'items' => $checkout->description,
                'currency' => $checkout->currency,
                'amount' => $checkout->formattedAmount(),
                'first_name' => $payer->firstName,
                'last_name' => $payer->lastName,
                'email' => $payer->email,
                'phone' => $payer->phone,
                'address' => $payer->address,
                'city' => $payer->city,
                'country' => $payer->country,
                'hash' => $this->checkoutHash($checkout),
            ],
        );
    }

    public function verifyNotification(array $payload): bool
    {
        foreach (['merchant_id', 'order_id', 'payhere_amount', 'payhere_currency', 'status_code', 'md5sig'] as $key) {
            if (! isset($payload[$key]) || ! is_scalar($payload[$key])) {
                return false;
            }
        }

        if (! hash_equals($this->merchantId, (string) $payload['merchant_id'])) {
            return false;
        }

        $expected = strtoupper(md5(
            $this->merchantId
            .$payload['order_id']
            .$payload['payhere_amount']
            .$payload['payhere_currency']
            .$payload['status_code']
            .$this->hashedSecret(),
        ));

        return hash_equals($expected, strtoupper((string) $payload['md5sig']));
    }

    public function charge(Checkout $checkout, PaymentMethod $card): ChargeResult
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post($this->baseUrl().'/merchant/v1/payment/charge', [
            'type' => 'PAYMENT',
            'order_id' => $checkout->order_id,
            'items' => $checkout->description,
            'currency' => $checkout->currency,
            'amount' => (float) $checkout->formattedAmount(),
            'customer_token' => $card->customer_token,
            'notify_url' => $this->notifyUrl(),
        ]));

        $data = (array) $response->json('data');

        if ((int) $response->json('status') !== 1 || ! isset($data['status_code'])) {
            return new ChargeResult(PaymentStatus::Failed, null, $this->message($response, 'The card was declined.'));
        }

        return new ChargeResult(
            status: PaymentStatus::fromPayHere((int) $data['status_code']),
            paymentId: isset($data['payment_id']) ? (string) $data['payment_id'] : null,
            message: isset($data['status_message']) ? mb_substr((string) $data['status_message'], 0, 500) : null,
        );
    }

    public function confirm(Checkout $checkout): ?bool
    {
        if (! $this->hasApiKey()) {
            return null;
        }

        try {
            $response = $this->send(fn (PendingRequest $http) => $http->get($this->baseUrl().'/merchant/v1/payment/search', [
                'order_id' => $checkout->order_id,
            ]));
        } catch (PaymentGatewayException $e) {
            Log::warning('PayHere retrieval failed; relying on the notification signature.', ['order_id' => $checkout->order_id, 'error' => $e->getMessage()]);

            return null;
        }

        if ((int) $response->json('status') !== 1) {
            return false;
        }

        foreach ((array) $response->json('data') as $record) {
            if (is_array($record)
                && ($record['status'] ?? null) === 'RECEIVED'
                && ($record['currency'] ?? null) === $checkout->currency
                && (int) round(((float) ($record['amount'] ?? 0)) * 100) === $checkout->amount) {
                return true;
            }
        }

        return false;
    }

    public function refund(Payment $payment, string $reason): string
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post($this->baseUrl().'/merchant/v1/payment/refund', [
            'payment_id' => $payment->payhere_payment_id,
            'description' => mb_substr($reason, 0, 255),
        ]));

        if ((int) $response->json('status') !== 1) {
            throw new PaymentGatewayException('PayHere refused the refund: '.$this->message($response, 'unknown error').'.');
        }

        return (string) ($response->json('data') ?? '');
    }

    private function baseUrl(): string
    {
        return $this->sandbox ? 'https://sandbox.payhere.lk' : 'https://www.payhere.lk';
    }

    private function notifyUrl(): string
    {
        return $this->notifyUrl ?: route('webhooks.payhere.notify');
    }

    private function hashedSecret(): string
    {
        return strtoupper(md5($this->merchantSecret));
    }

    /** hash = UPPER(md5(merchant_id + order_id + amount + currency + UPPER(md5(merchant_secret)))) */
    private function checkoutHash(Checkout $checkout): string
    {
        return strtoupper(md5($this->merchantId.$checkout->order_id.$checkout->formattedAmount().$checkout->currency.$this->hashedSecret()));
    }

    private function hasApiKey(): bool
    {
        return filled($this->appId) && filled($this->appSecret);
    }

    /**
     * A merchant API call with a bearer token (OAuth, cached for 9 of its 10 minutes).
     * A 401 refreshes the token once.
     *
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        if (! $this->hasApiKey()) {
            throw new PaymentGatewayException('Saved cards aren\'t set up yet: the PayHere API key is missing.');
        }

        try {
            $response = $call($this->api($this->accessToken()));

            if ($response->status() === 401) {
                Cache::forget(self::TOKEN_CACHE_KEY);
                $response = $call($this->api($this->accessToken()));
            }
        } catch (ConnectionException $e) {
            throw new PaymentGatewayException(previous: $e);
        }

        if ($response->serverError()) {
            throw new PaymentGatewayException;
        }

        return $response;
    }

    private function api(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->asJson()->timeout(20);
    }

    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 540, function (): string {
            $response = Http::withBasicAuth((string) $this->appId, (string) $this->appSecret)
                ->asForm()
                ->acceptJson()
                ->timeout(15)
                ->post($this->baseUrl().'/merchant/v1/oauth/token', ['grant_type' => 'client_credentials']);

            $token = $response->json('access_token');

            if (! $response->successful() || ! is_string($token) || $token === '') {
                Log::error('PayHere OAuth failed.', ['status' => $response->status()]);

                throw new PaymentGatewayException;
            }

            return $token;
        });
    }

    private function message(Response $response, string $fallback): string
    {
        $message = $response->json('data.status_message') ?? $response->json('msg');

        return is_string($message) && $message !== '' ? mb_substr($message, 0, 500) : $fallback;
    }
}
