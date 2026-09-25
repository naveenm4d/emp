<?php

namespace App\Domains\Template\Services;

use App\Domains\Client\Models\Client;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Contracts\TemplateRepositoryInterface;
use App\Domains\Template\Enums\TemplateType;
use App\Domains\Template\Exceptions\TemplateNotAvailableException;
use App\Domains\Template\Models\Template;
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

    public function count(): int
    {
        return $this->templates->count();
    }
}
