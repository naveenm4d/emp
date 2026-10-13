<?php

namespace App\Domains\Billing\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Client\Models\ClientUser;

/** Who pays, as PayHere's checkout asks for it. */
final readonly class PayerData extends DataTransferObject
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public string $address,
        public string $city,
        public string $country,
    ) {}

    public static function for(ClientUser $user, BillingDetailsData $details): self
    {
        $name = trim($user->name);
        [$first, $last] = array_pad(preg_split('/\s+/', $name, 2) ?: [], 2, '');

        return new self(
            firstName: $first !== '' ? $first : $name,
            lastName: $last,
            email: $user->email,
            phone: $details->phone,
            address: $details->address,
            city: $details->city,
            country: $details->country,
        );
    }
}
