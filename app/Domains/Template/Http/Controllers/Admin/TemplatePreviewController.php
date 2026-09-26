<?php

namespace App\Domains\Template\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Template\Contracts\TemplatePreviewServiceInterface;
use App\Domains\Template\Models\Template;
use App\Domains\Template\Models\TemplateVersion;
use Inertia\Inertia;
use Inertia\Response;

class TemplatePreviewController extends InertiaController
{
    public function __invoke(Template $template, TemplateVersion $version, TemplatePreviewServiceInterface $previews): Response
    {
        return Inertia::render('admin/templates/preview', [
            'template' => ['id' => $template->id, 'name' => $template->name, 'key' => $template->key],
            'version' => $version->version,
            'design' => $previews->render($version),
        ]);
    }
}
