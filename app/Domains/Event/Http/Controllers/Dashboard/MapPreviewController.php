<?php

namespace App\Domains\Event\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Controller;
use App\Domains\Event\Contracts\MapPreviewServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The embeddable map for the map link typed into the event form (JSON). */
class MapPreviewController extends Controller
{
    public function __invoke(Request $request, MapPreviewServiceInterface $maps): JsonResponse
    {
        $validated = $request->validate(['url' => ['required', 'string', 'max:2048']]);

        return response()->json(['embed_url' => $maps->embedUrl($validated['url'])]);
    }
}
