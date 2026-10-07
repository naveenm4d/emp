<?php

namespace App\Domains\Template\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Template\Contracts\TemplatePreviewServiceInterface;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Exceptions\TemplateNotAvailableException;
use App\Domains\Template\Http\Requests\TemplatePreviewRequest;
use App\Domains\Template\Http\Resources\TemplateResource;
use App\Domains\Template\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The invitation designs a client can choose from, and their live previews. */
class TemplateController extends InertiaController
{
    use ResolvesClient;

    public function __construct(
        private readonly TemplateQueryServiceInterface $templates,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('client/templates/index', [
            'templates' => TemplateResource::collection($this->templates->available($this->client($request))),
        ]);
    }

    public function preview(TemplatePreviewRequest $request, Template $template, TemplatePreviewServiceInterface $previews): JsonResponse
    {
        try {
            $template = $this->templates->findSelectable($template->id, $this->client($request));
        } catch (TemplateNotAvailableException) {
            abort(404);
        }

        return response()->json($previews->forTemplate($template, $request->details()));
    }
}
