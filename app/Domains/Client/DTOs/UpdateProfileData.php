<?php

namespace App\Domains\Client\DTOs;

use App\Core\DTOs\PartialData;

final class UpdateProfileData extends PartialData
{
    protected static function fields(): array
    {
        return ['name', 'email'];
    }

    protected function normalize(array $values): array
    {
        if (isset($values['email'])) {
            $values['email'] = mb_strtolower(trim($values['email']));
        }

        return $values;
    }
}
