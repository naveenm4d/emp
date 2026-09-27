<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the dashboard theme (light, dark or system) with the root views so
 * the first paint already matches the viewer's choice. The choice lives in
 * the unencrypted `appearance` cookie that the frontend writes.
 */
class HandleAppearance
{
    public const COOKIE = 'appearance';

    public const DEFAULT = 'light';

    /** @var list<string> */
    public const OPTIONS = ['light', 'dark', 'system'];

    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie(self::COOKIE);

        View::share('appearance', in_array($appearance, self::OPTIONS, true) ? $appearance : self::DEFAULT);

        return $next($request);
    }
}
