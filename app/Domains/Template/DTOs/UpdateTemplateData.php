<?php

namespace App\Domains\Template\DTOs;

use App\Core\DTOs\PartialData;

/** Catalogue details staff can change; the template's code comes only from packages. */
final class UpdateTemplateData extends PartialData
{
    protected static function fields(): array
    {
        return [
            'name', 'description', 'category', 'tags', 'price', 'currency',
            'display_price', 'sort_order', 'is_active',
        ];
    }

    protected function normalize(array $values): array
    {
        if (array_key_exists('tags', $values)) {
            $values['tags'] = array_values(array_unique(array_filter(array_map(
                fn (mixed $tag) => mb_strtolower(trim((string) $tag)),
                (array) $values['tags'],
            ))));
        }

        if (array_key_exists('currency', $values)) {
            $values['currency'] = strtoupper((string) $values['currency']);
        }

        foreach (['price', 'sort_order'] as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = (int) $values[$key];
            }
        }

        if (array_key_exists('is_active', $values)) {
            $values['is_active'] = (bool) $values['is_active'];
        }

        return $values;
    }
}
