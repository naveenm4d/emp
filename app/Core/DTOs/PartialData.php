<?php

namespace App\Core\DTOs;

/**
 * Input for partial (PATCH-style) updates. Only keys that were actually
 * provided are kept, so "not sent" and "sent as null" stay distinguishable.
 */
abstract class PartialData
{
    /** @var array<string, mixed> */
    private readonly array $values;

    /** @param array<string, mixed> $data */
    final public function __construct(array $data)
    {
        $this->values = $this->normalize(
            array_intersect_key($data, array_flip(static::fields())),
        );
    }

    /** @return list<string> the attributes this DTO accepts */
    abstract protected static function fields(): array;

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    /**
     * Hook for subclasses to cast / clean the provided values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function normalize(array $values): array
    {
        return $values;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->values, array_flip($keys));
    }
}
