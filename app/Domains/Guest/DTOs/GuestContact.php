<?php

namespace App\Domains\Guest\DTOs;

/**
 * Canonical forms for guest contact details, so duplicate detection
 * compares like with like.
 */
final class GuestContact
{
    public static function email(mixed $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /** Keeps a leading "+" and digits only: "+1 (555) 010-0200" => "+15550100200". */
    public static function phone(mixed $phone): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return (str_starts_with($phone, '+') ? '+' : '').$digits;
    }
}
