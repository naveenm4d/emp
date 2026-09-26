<?php

namespace App\Domains\Template\Contracts;

use App\Domains\Client\Models\Client;
use App\Domains\Template\DTOs\TemplateFilters;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TemplateQueryServiceInterface
{
    /** @return Collection<int, Template> the catalogue a client can pick from */
    public function available(?Client $client): Collection;

    /** A template the client may pick, with its latest version loaded. */
    public function findSelectable(string $templateId, ?Client $client): Template;

    public function count(): int;

    /** @return LengthAwarePaginator<int, Template> the whole catalogue, for staff */
    public function search(TemplateFilters $filters): LengthAwarePaginator;

    /** @return Collection<int, TemplateVersion> newest first, with events_count */
    public function versions(Template $template): Collection;
}
