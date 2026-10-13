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

    /** A template the client may pick (and preview), with its latest version loaded. */
    public function findSelectable(string $templateId, ?Client $client): Template;

    /** Free, the client's own, bought, or already used by one of their events. */
    public function isUsableBy(Template $template, Client $client): bool;

    /** Throws TemplatePurchaseRequiredException for a paid template the client hasn't bought. */
    public function ensureUsable(Template $template, Client $client): void;

    /** @return list<string> ids of the templates the client bought or already uses (paid ones are usable) */
    public function ownedIds(Client $client): array;

    public function count(): int;

    /** @return LengthAwarePaginator<int, Template> the whole catalogue, for staff */
    public function search(TemplateFilters $filters): LengthAwarePaginator;

    /** @return Collection<int, TemplateVersion> newest first, with events_count */
    public function versions(Template $template): Collection;
}
