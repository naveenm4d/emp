<?php

/*
|--------------------------------------------------------------------------
| Staff console  (/admin)
|--------------------------------------------------------------------------
| Inertia pages for EMP platform staff. Loaded from bootstrap/app.php with
| the "web" middleware, the /admin prefix and the "admin." name prefix.
*/

use App\Domains\Client\Http\Controllers\Admin\ClientController;
use App\Domains\Event\Http\Controllers\Admin\EventController;
use App\Domains\Event\Http\Controllers\Admin\EventRegistrationController;
use App\Domains\Event\Http\Controllers\Admin\EventStateController;
use App\Domains\Event\Http\Controllers\Admin\MapPreviewController;
use App\Domains\Notification\Http\Controllers\Admin\FailedNotificationController;
use App\Domains\Staff\Http\Controllers\Admin\AuthController;
use App\Domains\Staff\Http\Controllers\Admin\DashboardController;
use App\Domains\Staff\Http\Controllers\Admin\StaffMemberController;
use App\Domains\Template\Http\Controllers\Admin\ClientTemplatePreviewController;
use App\Domains\Template\Http\Controllers\Admin\TemplateController;
use App\Domains\Template\Http\Controllers\Admin\TemplatePreviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:staff')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware(['auth:staff', 'staff.active'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('staff', [StaffMemberController::class, 'index'])->middleware('can:staff.read')->name('staff.index');
    Route::get('staff/create', [StaffMemberController::class, 'create'])->middleware('can:staff.create')->name('staff.create');
    Route::post('staff', [StaffMemberController::class, 'store'])->middleware('can:staff.create')->name('staff.store');

    Route::get('clients', [ClientController::class, 'index'])->middleware('can:clients.read')->name('clients.index');

    // A client's account and their events, managed on the client's behalf.
    // Scoped bindings: an {event} must belong to the {client} in the URL.
    Route::prefix('clients/{client}')->name('clients.')->scopeBindings()->group(function () {
        Route::get('/', [ClientController::class, 'show'])->middleware('can:clients.read')->name('show');
        Route::patch('/', [ClientController::class, 'update'])->middleware('can:clients.update')->name('update');

        Route::get('events/create', [EventController::class, 'create'])->middleware('can:events.create')->name('events.create');
        Route::get('templates/{template}/preview', ClientTemplatePreviewController::class)
            ->middleware('can:events.create')
            ->withoutScopedBindings() // templates are not owned by the client; the controller checks availability
            ->name('templates.preview');
        Route::post('events', [EventController::class, 'store'])->middleware('can:events.create')->name('events.store');

        Route::middleware('can:events.update')->group(function () {
            Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
            Route::patch('events/{event}', [EventController::class, 'update'])->name('events.update');
            Route::patch('events/{event}/state', [EventStateController::class, 'update'])->name('events.state');
            Route::post('events/{event}/registration/open', [EventRegistrationController::class, 'open'])->name('events.registration.open');
            Route::post('events/{event}/registration/close', [EventRegistrationController::class, 'close'])->name('events.registration.close');
        });

        Route::delete('events/{event}', [EventController::class, 'destroy'])->middleware('can:events.delete')->name('events.destroy');
    });

    Route::get('events', [EventController::class, 'index'])->middleware('can:events.read')->name('events.index');
    Route::get('map-preview', MapPreviewController::class)->middleware(['can:events.read', 'throttle:30,1'])->name('map-preview');

    // Template catalogue: packages are uploaded as zips, details edited here.
    Route::get('templates', [TemplateController::class, 'index'])->middleware('can:templates.read')->name('templates.index');
    Route::post('templates', [TemplateController::class, 'store'])->middleware('can:templates.manage')->name('templates.store');
    Route::get('templates/{template}', [TemplateController::class, 'show'])->middleware('can:templates.read')->name('templates.show');
    Route::patch('templates/{template}', [TemplateController::class, 'update'])->middleware('can:templates.manage')->name('templates.update');
    Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->middleware('can:templates.manage')->name('templates.destroy');
    Route::get('templates/{template}/versions/{version}/preview', TemplatePreviewController::class)
        ->middleware('can:templates.read')
        ->scopeBindings()
        ->name('templates.versions.preview');

    Route::get('notifications/failed', [FailedNotificationController::class, 'index'])->middleware('can:notifications.read')->name('notifications.failed');
    Route::post('notifications/{notification}/retry', [FailedNotificationController::class, 'retry'])->middleware('can:notifications.retry')->name('notifications.retry');
});
