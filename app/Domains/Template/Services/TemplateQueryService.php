<?php

namespace App\Domains\Template\Services;

use App\Domains\Client\Models\Client;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Contracts\TemplateRepositoryInterface;
use App\Domains\Template\DTOs\TemplateFilters;
use App\Domains\Template\Enums\TemplateType;
use App\Domains\Template\Exceptions\TemplateNotAvailableException;
use App\Domains\Template\Exceptions\TemplatePurchaseRequiredException;
use App\Domains\Template\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TemplateQueryService implements TemplateQueryServiceInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templates,
    ) {}

    public function available(?Client $client): Collection
    {
        return $this->templates->available($client?->id);
    }

    public function findSelectable(string $templateId, ?Client $client): Template
    {
        /** @var Template|null $template */
        $template = $this->templates->find($templateId);

        $selectable = $template
            && $template->is_active
            && $template->latest_version_id !== null
            && ($template->type === TemplateType::Predefined || $template->client_id === $client?->id);

        if (! $selectable) {
            throw new TemplateNotAvailableException;
        }

        return $template->load('latestVersion');
    }

    public function isUsableBy(Template $template, Client $client): bool
    {
        return $template->isFree()
            || $template->client_id === $client->id
            || $this->templates->isOwnedBy($template, $client->id);
    }

    public function ensureUsable(Template $template, Client $client): void
    {
        if (! $this->isUsableBy($template, $client)) {
            throw TemplatePurchaseRequiredException::for($template);
        }
    }

    public function ownedIds(Client $client): array
    {
        return $this->templates->ownedIds($client->id);
    }

    public function count(): int
    {
        return $this->templates->count();
    }

    public function search(TemplateFilters $filters): LengthAwarePaginator
    {
        return $this->templates->search($filters, config('emp.per_page'));
    }

    public function versions(Template $template): Collection
    {
        return $this->templates->versionsWithUsage($template);
    }
}
