<?php

namespace App\Domains\Template\Contracts;

use App\Domains\Client\Models\Client;
use App\Domains\Template\Models\Template;
use Illuminate\Support\Collection;

interface TemplateQueryServiceInterface
{
    /** @return Collection<int, Template> the catalogue a client can pick from */
    public function available(?Client $client): Collection;

    /** A template the client may pick, with its latest version loaded. */
    public function findSelectable(string $templateId, ?Client $client): Template;

    public function count(): int;
}
