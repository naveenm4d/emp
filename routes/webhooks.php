<?php

/*
|--------------------------------------------------------------------------
| Provider webhooks  (/webhooks)
|--------------------------------------------------------------------------
| Machine-to-machine callbacks. Loaded without the `web` group: no session,
| no CSRF (Meta signs the raw body instead).
*/

use App\Domains\Notification\Http\Controllers\Webhook\WhatsAppWebhookController;
use App\Domains\Notification\Http\Middleware\VerifyWhatsAppSignature;
use Illuminate\Support\Facades\Route;

Route::get('whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.verify');
Route::post('whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->middleware(VerifyWhatsAppSignature::class)
    ->name('whatsapp.handle');
