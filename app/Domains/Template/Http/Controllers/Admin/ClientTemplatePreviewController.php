<?php

namespace App\Domains\Template\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Models\Client;
use App\Domains\Template\Contracts\TemplatePreviewServiceInterface;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Exceptions\TemplateNotAvailableException;
use App\Domains\Template\Http\Requests\TemplatePreviewRequest;
use App\Domains\Template\Models\Template;
use Illuminate\Http\JsonResponse;

/** Staff preview a template as the client would see it while creating their event. */
class ClientTemplatePreviewController extends InertiaController
{
    public function __invoke(
        TemplatePreviewRequest $request,
        Client $client,
        Template $template,
        TemplateQueryServiceInterface $templates,
        TemplatePreviewServiceInterface $previews,
    ): JsonResponse {
        try {
            $template = $templates->findSelectable($template->id, $client);
        } catch (TemplateNotAvailableException) {
            abort(404);
        }

        return response()->json($previews->forTemplate($template, $request->details()));
    }
}
