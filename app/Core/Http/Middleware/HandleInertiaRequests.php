<?php

namespace App\Core\Http\Middleware;

use App\Domains\Client\Http\Resources\ClientResource;
use App\Domains\Staff\Http\Resources\StaffMemberResource;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Each area has its own root template:
     *  - resources/views/web.blade.php       guest-facing site (/, /e, /rsvp, /preview)
     *  - resources/views/app.blade.php       client dashboard  (/app)
     *  - resources/views/admin.blade.php     staff console     (/admin)
     */
    public function rootView(Request $request): string
    {
        return match (self::area($request)) {
            'admin' => 'admin',
            'client' => 'app',
            default => 'web',
        };
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'area' => self::area($request),
            'auth' => self::area($request) === 'admin'
                ? ['staff' => fn () => ($staff = $request->user('staff')) ? StaffMemberResource::make($staff)->resolve($request) : null]
                : ['client' => fn () => ($client = $request->user('client')) ? ClientResource::make($client)->resolve($request) : null],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /** @return 'web'|'client'|'admin' */
    private static function area(Request $request): string
    {
        return match (true) {
            $request->is('admin', 'admin/*') => 'admin',
            $request->is('app', 'app/*') => 'client',
            default => 'web',
        };
    }
}
