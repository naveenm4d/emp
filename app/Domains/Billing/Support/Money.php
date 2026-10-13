<?php

namespace App\Domains\Billing\Support;

/** Amounts are stored in minor units (cents); LKR shows as "Rs. 1,490". */
final class Money
{
    public static function format(int $amount, string $currency = 'LKR'): string
    {
        $value = $amount % 100 === 0
            ? number_format(intdiv($amount, 100))
            : number_format($amount / 100, 2);

        return ($currency === 'LKR' ? 'Rs.' : $currency).' '.$value;
    }
}
