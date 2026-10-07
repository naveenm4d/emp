<?php

namespace App\Core\Http\Middleware;

use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\Http\Resources\ClientResource;
use App\Domains\Client\Http\Resources\ClientUserResource;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Staff\Http\Resources\StaffMemberResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
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
                : [
                    // The account the signed-in user acts for.
                    'client' => fn () => ($user = self::clientUser($request)) ? [
                        ...ClientResource::make($user->client)->resolve($request),
                        // The plan and what's used / left (events, guests, users, features).
                        'plan' => app(ClientPlanServiceInterface::class)->usage($user->client),
                    ] : null,
                    // Who is signed in: their role, permissions and whether they see every event.
                    'user' => fn () => ($user = self::clientUser($request)) ? ClientUserResource::make($user)->resolve($request) : null,
                ],
            // Staff sidebar badge: messages that failed to send.
            'failedMessages' => fn () => self::area($request) === 'admin' && $request->user('staff')?->can('notifications.read')
                ? app(NotificationQueryServiceInterface::class)->countFailed()
                : null,
            // Events for the switcher in the event header; loaded on demand (router.reload({ only: ['eventSwitcher'] })).
            'eventSwitcher' => Inertia::optional(fn () => ($user = self::clientUser($request))
                ? EventResource::collection(app(EventQueryServiceInterface::class)->forSwitcher($user))->resolve($request)
                : []),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    private static function clientUser(Request $request): ?ClientUser
    {
        $user = $request->user('client');

        return $user instanceof ClientUser ? $user : null;
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
