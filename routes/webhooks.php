<?php

/*
|--------------------------------------------------------------------------
| Provider webhooks  (/webhooks)
|--------------------------------------------------------------------------
| Machine-to-machine callbacks. Loaded without the `web` group: no session,
| no CSRF (Meta signs the raw body; PayHere signs its fields with md5sig;
| the deploy workflow sends a bearer token).
*/

use App\Core\Http\Controllers\Webhook\DeploymentController;
use App\Core\Http\Middleware\VerifyDeployToken;
use App\Domains\Billing\Http\Controllers\Webhook\PayHereWebhookController;
use App\Domains\Notification\Http\Controllers\Webhook\WhatsAppWebhookController;
use App\Domains\Notification\Http\Middleware\VerifyWhatsAppSignature;
use Illuminate\Support\Facades\Route;

Route::get('whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
Route::post('whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->middleware(VerifyWhatsAppSignature::class)
    ->name('whatsapp.handle');

// PayHere's notify_url: payment results (the signature and amount are checked in the service).
Route::post('payhere', [PayHereWebhookController::class, 'notify'])
    ->middleware('throttle:120,1')
    ->name('payhere.notify');

// The deploy workflow, around its FTP upload (reachable during maintenance, see bootstrap/app.php).
Route::middleware([VerifyDeployToken::class, 'throttle:10,1'])->prefix('deploy')->name('deploy.')->group(function () {
    Route::post('down', [DeploymentController::class, 'down'])->name('down');
    Route::post('finish', [DeploymentController::class, 'finish'])->name('finish');
});
