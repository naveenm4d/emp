<?php

namespace App\Domains\Template\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Template\Enums\TemplateCategory;

final readonly class TemplateFilters extends DataTransferObject
{
    /** @param  bool|null  $active  null = both */
    public function __construct(
        public ?string $search = null,
        public ?TemplateCategory $category = null,
        public ?bool $active = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            search: filled($data['search'] ?? null) ? (string) $data['search'] : null,
            category: TemplateCategory::tryFrom((string) ($data['category'] ?? '')),
            active: match ($data['status'] ?? null) {
                'active' => true,
                'inactive' => false,
                default => null,
            },
        );
    }

    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'category' => $this->category?->value,
            'status' => $this->active === null ? null : ($this->active ? 'active' : 'inactive'),
        ];
    }
}
