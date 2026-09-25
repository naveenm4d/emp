<?php

use App\Core\Exceptions\DomainException;
use App\Core\Http\Middleware\HandleInertiaRequests;
use App\Domains\Staff\Http\Middleware\EnsureStaffIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
|--------------------------------------------------------------------------
| EMP interfaces
|--------------------------------------------------------------------------
|  routes/web.php       /, /e, /rsvp, /preview  Guest-facing site (Inertia, public)
|  routes/web.php       /app/*       Client dashboard (Inertia, guard: client)
|  routes/internal.php  /internal/*  Staff console    (Inertia, guard: staff)
|  routes/webhooks.php  /webhooks/*  Provider callbacks (no session / CSRF)
*/

$isInternal = fn (Request $request): bool => $request->is('internal', 'internal/*');
$isSite = fn (Request $request): bool => ! $request->is('app', 'app/*', 'internal', 'internal/*', 'webhooks/*');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('internal')
                ->name('internal.')
                ->group(base_path('routes/internal.php'));

            Route::prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) use ($isInternal): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'staff.active' => EnsureStaffIsActive::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $isInternal($request)
            ? route('internal.login')
            : route('client.login'));

        $middleware->redirectUsersTo(fn (Request $request) => $isInternal($request)
            ? route('internal.dashboard')
            : route('client.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isSite): void {
        // Business-rule violations are expected outcomes, not errors worth logging.
        $exceptions->dontReport(DomainException::class);

        // Business-rule violations raised by services: a mutation goes back
        // with a flash error; a page load becomes the matching HTTP error.
        $exceptions->render(function (DomainException $e, Request $request) {
            if (! $request->isMethod('GET') && ! $request->expectsJson()) {
                return back()->with('error', $e->getMessage());
            }

            throw new HttpException($e->status(), $e->getMessage(), $e);
        });

        // Guests on the public site get friendly error pages instead of the
        // framework's (unknown RSVP token, expired preview link, …).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) use ($isSite) {
            if (! $isSite($request) || $request->expectsJson() || ! $request->isMethod('GET')) {
                return $response;
            }

            $status = $response->getStatusCode();

            [$page, $status] = match (true) {
                $status === 404 => ['site/errors/not-found', 404],
                $e instanceof InvalidSignatureException => ['site/errors/link-expired', 403],
                $status === 429 => ['site/errors/unavailable', 429],
                $status >= 500 && ! config('app.debug') => ['site/errors/unavailable', 503],
                default => [null, $status],
            };

            if ($page === null) {
                return $response;
            }

            Inertia::setRootView('site');

            return Inertia::render($page)->toResponse($request)->setStatusCode($status);
        });
    })->create();
