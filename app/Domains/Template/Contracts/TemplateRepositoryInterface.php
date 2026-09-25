<?php

namespace App\Domains\Template\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<Template>
 */
interface TemplateRepositoryInterface extends RepositoryInterface
{
    /** @return Collection<int, Template> active templates with a published version, predefined or owned by the client */
    public function available(?string $clientId): Collection;

    public function findByKey(string $key): ?Template;

    public function findVersion(string $templateId, string $version): ?TemplateVersion;

    /** @param array<string, mixed> $attributes */
    public function createVersion(array $attributes): TemplateVersion;
}
