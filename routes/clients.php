<?php

/*
|--------------------------------------------------------------------------
| Client dashboard  (/app)
|--------------------------------------------------------------------------
| Inertia pages for subscribed clients who create and manage events. Loaded
| from bootstrap/app.php with the "web" middleware, the /app prefix and the
| "client." name prefix.
*/

use App\Domains\Client\Http\Controllers\Dashboard\AuthController;
use App\Domains\Client\Http\Controllers\Dashboard\InvitationController;
use App\Domains\Client\Http\Controllers\Dashboard\MembershipController;
use App\Domains\Client\Http\Controllers\Dashboard\PasswordResetController;
use App\Domains\Client\Http\Controllers\Dashboard\ProfileController;
use App\Domains\Client\Http\Controllers\Dashboard\TeamController;
use App\Domains\Event\Http\Controllers\Dashboard\EventController;
use App\Domains\Event\Http\Controllers\Dashboard\EventDesignController;
use App\Domains\Event\Http\Controllers\Dashboard\EventMediaController;
use App\Domains\Event\Http\Controllers\Dashboard\EventRegistrationController;
use App\Domains\Event\Http\Controllers\Dashboard\EventSettingsController;
use App\Domains\Event\Http\Controllers\Dashboard\EventStateController;
use App\Domains\Event\Http\Controllers\Dashboard\HomeController;
use App\Domains\Event\Http\Controllers\Dashboard\MapPreviewController;
use App\Domains\Guest\Http\Controllers\Dashboard\GuestApprovalController;
use App\Domains\Guest\Http\Controllers\Dashboard\GuestController;
use App\Domains\Notification\Http\Controllers\Dashboard\NotificationController;
use App\Domains\Rsvp\Http\Controllers\Dashboard\RsvpController;
use App\Domains\Rsvp\Http\Controllers\Dashboard\RsvpDeliveryController;
use App\Domains\Seating\Http\Controllers\Dashboard\SeatingController;
use App\Domains\Seating\Http\Controllers\Dashboard\VenueElementController;
use App\Domains\Template\Http\Controllers\Dashboard\TemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:client')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');

    Route::get('forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.store');

    // Joining an account from an invitation email
    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('invitation', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitation.accept');
});

Route::middleware('auth:client')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', HomeController::class)->name('dashboard');

    // Events
    Route::resource('events', EventController::class);
    Route::patch('events/{event}/state', [EventStateController::class, 'update'])->name('events.state');
    Route::post('events/{event}/registration/open', [EventRegistrationController::class, 'open'])->name('events.registration.open');
    Route::post('events/{event}/registration/close', [EventRegistrationController::class, 'close'])->name('events.registration.close');
    Route::get('map-preview', MapPreviewController::class)->middleware('throttle:30,1')->name('map-preview');

    // Invitation editor: the template's editable texts, colours, sections and media
    Route::get('events/{event}/design', [EventDesignController::class, 'show'])->name('events.design');
    Route::patch('events/{event}/design', [EventDesignController::class, 'update'])->name('events.design.update');
    Route::post('events/{event}/design/preview', [EventDesignController::class, 'preview'])->name('events.design.preview');
    Route::patch('events/{event}/design/template', [EventDesignController::class, 'changeTemplate'])->name('events.design.template');
    Route::post('events/{event}/template/upgrade', [EventDesignController::class, 'upgrade'])->name('events.template.upgrade');
    Route::post('events/{event}/media', [EventMediaController::class, 'store'])->name('events.media.store');
    Route::delete('events/{event}/media/{media}', [EventMediaController::class, 'destroy'])->scopeBindings()->name('events.media.destroy');

    // Settings: what guests are asked when they register or RSVP
    Route::get('events/{event}/settings', [EventSettingsController::class, 'edit'])->name('events.settings');
    Route::put('events/{event}/settings', [EventSettingsController::class, 'update'])->name('events.settings.update');

    // Seating: tables and who sits where (not on the Starter plan; the page shows an upgrade prompt)
    Route::get('events/{event}/seating', [SeatingController::class, 'index'])->name('events.seating');
    Route::middleware('plan.feature:seating')->group(function () {
        Route::post('events/{event}/tables', [SeatingController::class, 'storeTable'])->name('events.tables.store');
        Route::patch('tables/{table}', [SeatingController::class, 'updateTable'])->name('tables.update');
        Route::delete('tables/{table}', [SeatingController::class, 'destroyTable'])->name('tables.destroy');
        Route::post('tables/{table}/seats', [SeatingController::class, 'assign'])->name('tables.seats.store');
        Route::delete('guests/{guest}/seats', [SeatingController::class, 'unassign'])->name('guests.seats.destroy');
        Route::post('events/{event}/seating/swap', [SeatingController::class, 'swap'])->name('events.seating.swap');
        Route::post('events/{event}/seating/replace', [SeatingController::class, 'replace'])->name('events.seating.replace');
        Route::post('events/{event}/seating/auto', [SeatingController::class, 'autoSeat'])->name('events.seating.auto');

        // Floor plan: where tables stand, and venue elements (stage, poruwa, buffet, …)
        Route::patch('events/{event}/seating/layout', [SeatingController::class, 'layout'])->name('events.seating.layout');
        Route::post('events/{event}/venue-elements', [VenueElementController::class, 'store'])->name('events.venue-elements.store');
        Route::patch('venue-elements/{venueElement}', [VenueElementController::class, 'update'])->name('venue-elements.update');
        Route::delete('venue-elements/{venueElement}', [VenueElementController::class, 'destroy'])->name('venue-elements.destroy');
    });

    // Guests
    Route::get('events/{event}/guests', [GuestController::class, 'index'])->name('events.guests.index');
    Route::post('events/{event}/guests', [GuestController::class, 'store'])->name('events.guests.store');
    Route::patch('guests/{guest}', [GuestController::class, 'update'])->name('guests.update');
    Route::delete('guests/{guest}', [GuestController::class, 'destroy'])->name('guests.destroy');
    Route::post('guests/{guest}/approve', [GuestApprovalController::class, 'approve'])->name('guests.approve');
    Route::post('guests/{guest}/reject', [GuestApprovalController::class, 'reject'])->name('guests.reject');
    Route::post('guests/{guest}/waitlist', [GuestApprovalController::class, 'waitlist'])->name('guests.waitlist');

    // RSVP links sent to guests
    Route::get('events/{event}/rsvps', [RsvpController::class, 'index'])->name('events.rsvps.index');
    Route::post('guests/{guest}/rsvps', [RsvpController::class, 'store'])->name('guests.rsvps.store');
    Route::post('rsvps/{rsvp}/send', [RsvpDeliveryController::class, 'send'])->name('rsvps.send');
    Route::post('rsvps/{rsvp}/resend', [RsvpDeliveryController::class, 'resend'])->name('rsvps.resend');
    Route::post('rsvps/{rsvp}/remind', [RsvpDeliveryController::class, 'remind'])->name('rsvps.remind');
    Route::post('rsvps/{rsvp}/expire', [RsvpDeliveryController::class, 'expire'])->name('rsvps.expire');

    // Notifications: the message log (not on the Starter plan; the page shows an upgrade prompt)
    Route::get('events/{event}/notifications', [NotificationController::class, 'index'])->name('events.notifications.index');
    Route::middleware('plan.feature:message_log')->group(function () {
        Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');
    });

    // Invitation designs: browse and preview
    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('templates/{template}/preview', [TemplateController::class, 'preview'])->name('templates.preview');

    // Profile
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Membership: the client's plan and what it includes
    Route::get('membership', MembershipController::class)->name('membership');

    // Team: who else signs in to the account, what they can do and which events they see
    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::post('team', [TeamController::class, 'store'])->name('team.store');
    Route::patch('team/{member}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');
    Route::post('team/{member}/resend', [TeamController::class, 'resend'])->middleware('throttle:6,1')->name('team.resend');
    Route::post('team/{member}/owner', [TeamController::class, 'transferOwnership'])->name('team.owner');
});
