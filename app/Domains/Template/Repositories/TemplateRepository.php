<?php

namespace App\Domains\Template\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Event\Models\Event;
use App\Domains\Template\Contracts\TemplateRepositoryInterface;
use App\Domains\Template\DTOs\TemplateFilters;
use App\Domains\Template\Enums\TemplateCategory;
use App\Domains\Template\Enums\TemplateType;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function search(TemplateFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with(['latestVersion', 'client:id,name'])
            ->withCount('versions')
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('key', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('author', "%{$search}%", caseSensitive: false),
            ))
            ->when($filters->category, fn (Builder $query, TemplateCategory $category) => $query->where('category', $category))
            ->when($filters->active !== null, fn (Builder $query) => $query->where('is_active', $filters->active))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function versionsWithUsage(Template $template): Collection
    {
        return $template->versions()
            ->withCount('events')
            ->orderByDesc('published_at')
            ->get();
    }

    public function isInUse(Template $template): bool
    {
        return Event::query()
            ->whereIn('template_version_id', $template->versions()->select('id'))
            ->exists();
    }
}
