<?php

namespace App\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Base class for Inertia dashboard controllers (client + staff).
 */
abstract class InertiaController extends Controller
{
    protected function backWithSuccess(string $message): RedirectResponse
    {
        return back()->with('success', $message);
    }

    /** @param mixed $parameters route parameters (a model, id or array) */
    protected function toRouteWithSuccess(string $route, string $message, mixed $parameters = []): RedirectResponse
    {
        return to_route($route, $parameters)->with('success', $message);
    }
}
