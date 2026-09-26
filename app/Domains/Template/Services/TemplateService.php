<?php

namespace App\Domains\Template\Services;

use App\Core\Services\BaseService;
use App\Domains\Template\Contracts\TemplateRepositoryInterface;
use App\Domains\Template\Contracts\TemplateServiceInterface;
use App\Domains\Template\DTOs\UpdateTemplateData;
use App\Domains\Template\Exceptions\TemplateInUseException;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Support\Facades\Storage;

class TemplateService extends BaseService implements TemplateServiceInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templates,
    ) {}

    public function update(Template $template, UpdateTemplateData $data): Template
    {
        /** @var Template */
        return $this->templates->update($template, $data->toArray());
    }

    public function delete(Template $template): void
    {
        if ($this->templates->isInUse($template)) {
            throw new TemplateInUseException;
        }

        /** @var list<TemplateVersion> $versions */
        $versions = $template->versions()->get(['id', 'path'])->all();

        // Versions cascade with the template row.
        $this->transaction(fn () => $this->templates->delete($template));

        foreach ($versions as $version) {
            Storage::disk((string) config('emp.template_disk'))->deleteDirectory($version->path);
        }
    }
}
