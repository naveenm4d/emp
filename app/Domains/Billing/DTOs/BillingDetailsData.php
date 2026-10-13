<?php

namespace App\Domains\Billing\DTOs;

use App\Core\DTOs\DataTransferObject;

/** The account's billing contact (clients.billing_details); PayHere needs it on every checkout. */
final readonly class BillingDetailsData extends DataTransferObject
{
    public function __construct(
        public string $phone,
        public string $address,
        public string $city,
        public string $country = 'Sri Lanka',
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            phone: trim((string) ($data['phone'] ?? '')),
            address: trim((string) ($data['address'] ?? '')),
            city: trim((string) ($data['city'] ?? '')),
            country: trim((string) ($data['country'] ?? '')) ?: 'Sri Lanka',
        );
    }

    /** @param array<string, mixed>|null $stored */
    public static function fromStored(?array $stored): ?self
    {
        if (empty($stored['phone']) || empty($stored['address']) || empty($stored['city'])) {
            return null;
        }

        return self::fromArray($stored);
    }
}
