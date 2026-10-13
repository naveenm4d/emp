<?php

namespace App\Domains\Billing\DTOs;

use App\Core\DTOs\DataTransferObject;

/** A card PayHere saved (preapproval notification): its token and the masked details. */
final readonly class CardDetails extends DataTransferObject
{
    public function __construct(
        public string $token,
        public ?string $holderName,
        public ?string $brand,
        public ?string $last4,
        public ?int $expiryMonth,
        public ?int $expiryYear,
    ) {}

    /**
     * From PayHere's notification: card_no "************4242", card_expiry "MMYY", method "VISA".
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromNotification(array $payload): ?self
    {
        $token = trim((string) ($payload['customer_token'] ?? ''));

        if ($token === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) ($payload['card_no'] ?? '')) ?? '';
        $expiry = preg_replace('/\D/', '', (string) ($payload['card_expiry'] ?? '')) ?? '';
        $month = strlen($expiry) === 4 ? (int) substr($expiry, 0, 2) : null;

        return new self(
            token: $token,
            holderName: self::clean($payload['card_holder_name'] ?? null, 255),
            brand: self::clean($payload['method'] ?? null, 20),
            last4: strlen($digits) >= 4 ? substr($digits, -4) : null,
            expiryMonth: $month !== null && $month >= 1 && $month <= 12 ? $month : null,
            expiryYear: strlen($expiry) === 4 ? 2000 + (int) substr($expiry, 2, 2) : null,
        );
    }

    private static function clean(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
