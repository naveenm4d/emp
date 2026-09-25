<?php

/*
|--------------------------------------------------------------------------
| Guest-facing site  (/, /e, /rsvp, /preview)
|--------------------------------------------------------------------------
| Public Inertia pages (root view: site). No authentication: guests act
| through public event slugs and RSVP tokens; previews are signed links.
*/

use App\Core\Http\Controllers\Site\HomeController;
use App\Core\Http\Middleware\ContentSecurityPolicy;
use App\Domains\Client\Http\Controllers\Dashboard\AuthController;
use App\Domains\Client\Http\Controllers\Dashboard\PasswordResetController;
use App\Domains\Client\Http\Controllers\Dashboard\ProfileController;
use App\Domains\Event\Http\Controllers\Dashboard\EventController;
use App\Domains\Event\Http\Controllers\Dashboard\EventDesignController;
use App\Domains\Event\Http\Controllers\Dashboard\EventMediaController;
use App\Domains\Event\Http\Controllers\Dashboard\EventRegistrationController;
use App\Domains\Event\Http\Controllers\Dashboard\EventStateController;
use App\Domains\Event\Http\Controllers\Site\EventPageController;
use App\Domains\Event\Http\Controllers\Site\PreviewController;
use App\Domains\Guest\Http\Controllers\Dashboard\GuestApprovalController;
use App\Domains\Guest\Http\Controllers\Dashboard\GuestController;
use App\Domains\Guest\Http\Controllers\Site\RegistrationController;
use App\Domains\Invitation\Http\Controllers\Dashboard\InvitationController;
use App\Domains\Invitation\Http\Controllers\Dashboard\InvitationDeliveryController;
use App\Domains\Invitation\Http\Controllers\Site\RsvpController;
use App\Domains\Notification\Http\Controllers\Dashboard\NotificationController;
use Illuminate\Support\Facades\Route;

Route::pattern('token', '[A-Fa-f0-9]{64}');

Route::middleware(ContentSecurityPolicy::class)->name('site.')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    // Public event page + self-registration
    Route::get('e/{slug}', [EventPageController::class, 'show'])->name('events.show');
    Route::post('e/{slug}/register', [RegistrationController::class, 'store'])
        ->middleware('throttle:public-registration')
        ->name('events.register');

    // The guest's invitation (link sent over WhatsApp)
    Route::get('rsvp/{token}', [RsvpController::class, 'show'])->name('rsvp.show');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('rsvp/{token}/accept', [RsvpController::class, 'accept'])->name('rsvp.accept');
        Route::post('rsvp/{token}/decline', [RsvpController::class, 'decline'])->name('rsvp.decline');
    });

    // Client preview of the generated invitation; the link is signed by the dashboard.
    Route::get('preview/{event}', [PreviewController::class, 'show'])
        ->middleware('signed')
        ->whereUuid('event')
        ->name('preview');
});

/*
|--------------------------------------------------------------------------
| Client dashboard  (/app)
|--------------------------------------------------------------------------
| Inertia pages for subscribed clients who create and manage events.
*/

Route::prefix('app')->name('client.')->group(function () {
    Route::middleware('guest:client')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::get('register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');

        Route::get('forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.store');
    });

    Route::middleware('auth:client')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::redirect('/', '/app/events')->name('dashboard');

        // Events
        Route::resource('events', EventController::class);
        Route::patch('events/{event}/state', [EventStateController::class, 'update'])->name('events.state');
        Route::post('events/{event}/registration/open', [EventRegistrationController::class, 'open'])->name('events.registration.open');
        Route::post('events/{event}/registration/close', [EventRegistrationController::class, 'close'])->name('events.registration.close');

        // Invitation design: template media slots + preview
        Route::get('events/{event}/design', [EventDesignController::class, 'show'])->name('events.design');
        Route::post('events/{event}/template/upgrade', [EventDesignController::class, 'upgrade'])->name('events.template.upgrade');
        Route::post('events/{event}/media', [EventMediaController::class, 'store'])->name('events.media.store');
        Route::delete('events/{event}/media/{media}', [EventMediaController::class, 'destroy'])->scopeBindings()->name('events.media.destroy');

        // Guests
        Route::get('events/{event}/guests', [GuestController::class, 'index'])->name('events.guests.index');
        Route::post('events/{event}/guests', [GuestController::class, 'store'])->name('events.guests.store');
        Route::patch('guests/{guest}', [GuestController::class, 'update'])->name('guests.update');
        Route::delete('guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');
        Route::post('guests/{guest}/approve', [GuestApprovalController::class, 'approve'])->name('guests.approve');
        Route::post('guests/{guest}/reject', [GuestApprovalController::class, 'reject'])->name('guests.reject');
        Route::post('guests/{guest}/waitlist', [GuestApprovalController::class, 'waitlist'])->name('guests.waitlist');

        // Invitations
        Route::get('events/{event}/invitations', [InvitationController::class, 'index'])->name('events.invitations.index');
        Route::post('guests/{guest}/invitations', [InvitationController::class, 'store'])->name('guests.invitations.store');
        Route::post('invitations/{invitation}/send', [InvitationDeliveryController::class, 'send'])->name('invitations.send');
        Route::post('invitations/{invitation}/resend', [InvitationDeliveryController::class, 'resend'])->name('invitations.resend');
        Route::post('invitations/{invitation}/expire', [InvitationDeliveryController::class, 'expire'])->name('invitations.expire');

        // Notifications
        Route::get('events/{event}/notifications', [NotificationController::class, 'index'])->name('events.notifications.index');
        Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');

        // Profile
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });
});
