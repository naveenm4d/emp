<?php

/*
|--------------------------------------------------------------------------
| Guest-facing site  (/, /{slug}/{code}, /preview)
|--------------------------------------------------------------------------
| Public Inertia pages (root view: web). No authentication: guests act
| through short links (the event's public URL or their own RSVP link);
| previews are signed links.
*/

use App\Core\Http\Controllers\Web\HomeController;
use App\Core\Http\Middleware\ContentSecurityPolicy;
use App\Domains\Event\Http\Controllers\Web\LegacyLinkController;
use App\Domains\Event\Http\Controllers\Web\LinkController;
use App\Domains\Event\Http\Controllers\Web\PreviewController;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Http\Controllers\Web\RegistrationController;
use App\Domains\Rsvp\Http\Controllers\Web\RsvpController;
use Illuminate\Support\Facades\Route;

Route::pattern('token', '[A-Fa-f0-9]{64}');
Route::pattern('code', '[a-z2-9]{8}');
// Any first segment except the app's own (event links are domain/{slug}/{code}).
Route::pattern('slug', '(?!(?:'.implode('|', Event::RESERVED_SLUGS).')(?![A-Za-z0-9_-]))[A-Za-z0-9_-]+');

Route::middleware(ContentSecurityPolicy::class)->name('web.')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    // Client preview of the generated invitation; the link is signed by the dashboard.
    Route::get('preview/{event}', [PreviewController::class, 'show'])
        ->middleware('signed')
        ->whereUuid('event')
        ->name('preview');

    // Links in older formats redirect to the short links.
    Route::get('e/{slug}', [LegacyLinkController::class, 'event'])->name('legacy.event');
    Route::get('e/{slug}/{guest}', [LegacyLinkController::class, 'guest'])->whereUuid('guest')->name('legacy.guest');
    Route::get('rsvp/{token}', [LegacyLinkController::class, 'rsvp'])->name('legacy.rsvp');

    // Short links, registered last: domain/{slug}/{code}. The event's public URL
    // (public page + self-registration) or a guest's RSVP link (sent over WhatsApp).
    Route::get('{slug}/{code}', LinkController::class)->middleware('throttle:60,1')->name('link');
    Route::post('{slug}/{code}/register', [RegistrationController::class, 'store'])
        ->middleware('throttle:public-registration')
        ->name('events.register');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('{slug}/{code}/respond', [RsvpController::class, 'respond'])->name('rsvp.respond');
    });
});
