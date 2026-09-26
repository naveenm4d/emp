<?php

use App\Core\Exceptions\DomainException;
use App\Core\Http\Middleware\EnsurePlanFeature;
use App\Core\Http\Middleware\HandleInertiaRequests;
use App\Domains\Staff\Http\Middleware\EnsureStaffIsActive;
use App\Domains\Staff\Http\Middleware\LogStaffActivity;
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
|  routes/clients.php   /app/*       Client dashboard (Inertia, guard: client)
|  routes/admin.php     /admin/*     Staff console    (Inertia, guard: staff)
|  routes/webhooks.php  /webhooks/*  Provider callbacks (no session / CSRF)
*/

$isAdmin = fn (Request $request): bool => $request->is('admin', 'admin/*');
$isWeb = fn (Request $request): bool => ! $request->is('app', 'app/*', 'admin', 'admin/*', 'webhooks/*');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Loaded after web.php so its global route patterns (e.g. {token}) apply here too.
            Route::middleware('web')
                ->prefix('app')
                ->name('client.')
                ->group(base_path('routes/clients.php'));

            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) use ($isAdmin): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'staff.active' => EnsureStaffIsActive::class,
            'staff.activity' => LogStaffActivity::class,
            'plan.feature' => EnsurePlanFeature::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $isAdmin($request)
            ? route('admin.login')
            : route('client.login'));

        $middleware->redirectUsersTo(fn (Request $request) => $isAdmin($request)
            ? route('admin.dashboard')
            : route('client.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isWeb): void {
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
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) use ($isWeb) {
            if (! $isWeb($request) || $request->expectsJson() || ! $request->isMethod('GET')) {
                return $response;
            }

            $status = $response->getStatusCode();

            [$page, $status] = match (true) {
                $status === 404 => ['web/errors/not-found', 404],
                $e instanceof InvalidSignatureException => ['web/errors/link-expired', 403],
                $status === 429 => ['web/errors/unavailable', 429],
                $status >= 500 && ! config('app.debug') => ['web/errors/unavailable', 503],
                default => [null, $status],
            };

            if ($page === null) {
                return $response;
            }

            Inertia::setRootView('web');

            return Inertia::render($page)->toResponse($request)->setStatusCode($status);
        });
    })->create();
