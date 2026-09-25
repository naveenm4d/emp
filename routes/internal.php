<?php

/*
|--------------------------------------------------------------------------
| Staff console  (/internal)
|--------------------------------------------------------------------------
| Inertia pages for EMP platform staff. Loaded from bootstrap/app.php with
| the "web" middleware, the /internal prefix and the "internal." name prefix.
*/

use App\Domains\Client\Http\Controllers\Internal\ClientController;
use App\Domains\Event\Http\Controllers\Internal\EventController;
use App\Domains\Notification\Http\Controllers\Internal\FailedNotificationController;
use App\Domains\Staff\Http\Controllers\Internal\AuthController;
use App\Domains\Staff\Http\Controllers\Internal\DashboardController;
use App\Domains\Staff\Http\Controllers\Internal\StaffMemberController;
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

    Route::get('events', [EventController::class, 'index'])->middleware('can:events.read')->name('events.index');

    Route::get('notifications/failed', [FailedNotificationController::class, 'index'])->middleware('can:notifications.read')->name('notifications.failed');
    Route::post('notifications/{notification}/retry', [FailedNotificationController::class, 'retry'])->middleware('can:notifications.retry')->name('notifications.retry');
});
