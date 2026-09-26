<?php

namespace App\Domains\Template\Http\Controllers\Admin;

use App\Core\Exceptions\DomainException;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Template\Contracts\TemplateImportServiceInterface;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Contracts\TemplateServiceInterface;
use App\Domains\Template\DTOs\TemplateFilters;
use App\Domains\Template\Enums\TemplateCategory;
use App\Domains\Template\Http\Requests\Admin\UpdateTemplateRequest;
use App\Domains\Template\Http\Requests\Admin\UploadTemplateRequest;
use App\Domains\Template\Http\Resources\TemplateResource;
use App\Domains\Template\Http\Resources\TemplateVersionResource;
use App\Domains\Template\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Staff manage the template catalogue: packages come in as zips, details are edited here. */
class TemplateController extends InertiaController
{
    public function __construct(
        private readonly TemplateQueryServiceInterface $templates,
        private readonly TemplateServiceInterface $templateService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = TemplateFilters::fromArray($request->query());

        return Inertia::render('admin/templates/index', [
            'templates' => TemplateResource::collection($this->templates->search($filters)),
            'filters' => $filters->toArray(),
            'categories' => TemplateCategory::options(),
        ]);
    }

    public function store(UploadTemplateRequest $request, TemplateImportServiceInterface $importer): RedirectResponse
    {
        try {
            $version = $importer->importUploaded($request->package());
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['package' => $e->getMessage()]);
        }

        $template = $version->template;

        return $this->toRouteWithSuccess('admin.templates.show', "Imported {$template->name} v{$version->version}.", $template);
    }

    public function show(Template $template): Response
    {
        $template->load(['latestVersion', 'client:id,name']);

        return Inertia::render('admin/templates/show', [
            'template' => TemplateResource::make($template),
            'versions' => TemplateVersionResource::collection(
                $this->templates->versions($template)->each->setRelation('template', $template),
            ),
            'categories' => TemplateCategory::options(),
        ]);
    }

    public function update(UpdateTemplateRequest $request, Template $template): RedirectResponse
    {
        $this->templateService->update($template, $request->toData());

        return $this->backWithSuccess('Template updated.');
    }

    public function destroy(Template $template): RedirectResponse
    {
        $this->templateService->delete($template);

        return $this->toRouteWithSuccess('admin.templates.index', "Deleted {$template->name}.");
    }
}
