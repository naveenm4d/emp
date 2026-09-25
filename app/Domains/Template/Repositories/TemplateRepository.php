<?php

namespace App\Domains\Template\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Template\Contracts\TemplateRepositoryInterface;
use App\Domains\Template\Enums\TemplateType;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<Template>
 */
class TemplateRepository extends BaseRepository implements TemplateRepositoryInterface
{
    protected function model(): string
    {
        return Template::class;
    }

    public function available(?string $clientId): Collection
    {
        return $this->query()
            ->with('latestVersion')
            ->where('is_active', true)
            ->whereNotNull('latest_version_id')
            ->where(fn (Builder $query) => $query
                ->where('type', TemplateType::Predefined)
                ->when($clientId, fn (Builder $query, string $id) => $query->orWhere('client_id', $id)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findByKey(string $key): ?Template
    {
        return $this->query()->where('key', $key)->first();
    }

    public function findVersion(string $templateId, string $version): ?TemplateVersion
    {
        return TemplateVersion::query()
            ->where('template_id', $templateId)
            ->where('version', $version)
            ->first();
    }

    public function createVersion(array $attributes): TemplateVersion
    {
        return TemplateVersion::query()->create($attributes);
    }
}
